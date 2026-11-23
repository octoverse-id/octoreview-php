<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use GuzzleHttp\Psr7\Utils;
use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\ErrorCode;
use Octoverse\Octoreview\Exception\ApiException;
use Octoverse\Octoreview\Exception\OctoreviewException;
use Octoverse\Octoreview\Exception\UploadException;
use Octoverse\Octoreview\Model\Media;
use Octoverse\Octoreview\Model\MediaUpload;
use Octoverse\Octoreview\Transport;
use Octoverse\Octoreview\Upload\PutFailedException;
use Octoverse\Octoreview\Upload\Uploader;
use Octoverse\Octoreview\UploadOptions;
use Psr\Http\Message\StreamInterface;

/**
 * Review images and videos. uploadFile, uploadBytes and uploadStream run the three upload steps:
 * createUpload (the intent), a PUT of the bytes to `upload->url` with exactly `upload->headers`,
 * then complete.
 */
final class MediaService extends Service
{
    private const CONTENT_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    ];

    public function __construct(Transport $transport, private readonly Uploader $uploader)
    {
        parent::__construct($transport);
    }

    /**
     * Uploads a file: intent, PUT and complete. The content type comes from the extension (.jpg,
     * .jpeg, .png, .webp, .mp4, .webm, .mov) and the size from the file.
     *
     * @throws UploadException
     */
    public function uploadFile(string $reviewId, string $path, ?UploadOptions $options = null): Media
    {
        $contentType = self::CONTENT_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        if ($contentType === null) {
            throw new UploadException(UploadException::STAGE_INTENT, $reviewId, reason: sprintf(
                'cannot tell the content type of %s; use uploadStream with one of %s',
                basename($path),
                implode(', ', array_unique(self::CONTENT_TYPES)),
            ));
        }
        $handle = is_file($path) ? @fopen($path, 'rb') : false;
        $size = $handle === false ? false : filesize($path);
        if ($handle === false || $size === false) {
            throw new UploadException(UploadException::STAGE_INTENT, $reviewId, reason: sprintf('cannot read %s', basename($path)));
        }
        $stream = Utils::streamFor($handle);
        try {
            return $this->uploadStream($reviewId, $stream, $contentType, $size, $options);
        } finally {
            $stream->close();
        }
    }

    /**
     * Uploads an in-memory file: intent, PUT and complete.
     *
     * @throws UploadException
     */
    public function uploadBytes(string $reviewId, string $data, string $contentType, ?UploadOptions $options = null): Media
    {
        return $this->uploadStream($reviewId, Utils::streamFor($data), $contentType, strlen($data), $options);
    }

    /**
     * Uploads `$sizeBytes` from a seekable stream (rewound before each PUT attempt): intent, PUT
     * and complete, returning the ready media. The PUT goes to the signed URL with only the
     * headers the server returned and an explicit Content-Length: never the service key, tenant
     * or namespace headers.
     *
     * @param StreamInterface|resource $stream
     *
     * @throws UploadException
     */
    public function uploadStream(string $reviewId, mixed $stream, string $contentType, int $sizeBytes, ?UploadOptions $options = null): Media
    {
        $options ??= UploadOptions::create();
        $stream = $stream instanceof StreamInterface ? $stream : Utils::streamFor($stream);
        if (!$stream->isSeekable()) {
            throw new \InvalidArgumentException('octoreview: uploadStream needs a seekable stream, so a failed PUT can be retried');
        }
        $kind = match (true) {
            str_starts_with($contentType, 'image/') => 'image',
            str_starts_with($contentType, 'video/') => 'video',
            default => throw new UploadException(UploadException::STAGE_INTENT, $reviewId, reason: sprintf('content type "%s" is neither an image nor a video', $contentType)),
        };
        $fields = array_filter([
            'client_upload_id' => $options->clientUploadId,
            'position' => $options->position,
            'width' => $options->width,
            'height' => $options->height,
            'duration_seconds' => $options->durationSeconds,
        ], static fn(mixed $value): bool => $value !== null);
        $call = $options->callOptions;

        try {
            $intent = $this->createUpload($reviewId, $kind, $contentType, $sizeBytes, $fields, $call);
        } catch (OctoreviewException $e) {
            throw new UploadException(UploadException::STAGE_INTENT, $reviewId, requestIds: self::requestIds(null, $e), previous: $e);
        }
        $media = $intent->media;
        $failure = static fn(string $stage, ?\Throwable $e, ?string $code = null, ?string $fix = null, ?string $reason = null): UploadException => new UploadException(
            $stage,
            $reviewId,
            $media->id,
            $media->uploadExpiresAt,
            self::requestIds($intent->meta->requestId, $e),
            $code,
            $fix,
            $e,
            $reason,
        );
        if ($intent->upload === null) {
            // A replayed intent whose upload already completed (or expired).
            if ($media->status === 'ready') {
                return $media;
            }
            throw $failure(UploadException::STAGE_INTENT, null, reason: 'this upload is no longer uploadable; create a new one');
        }

        try {
            $this->uploader->put($intent->upload, $stream, $sizeBytes, $options->progress);
        } catch (OctoreviewException $e) { // PutFailedException or TransportException
            $done = $this->cleanUp($reviewId, $media->id, $call);
            if ($done !== null) {
                return $done;
            }
            throw $e instanceof PutFailedException
                ? $failure(UploadException::STAGE_PUT, $e, $e->providerCode, $e->fix)
                : $failure(UploadException::STAGE_PUT, $e);
        }

        try {
            return $this->complete($reviewId, $media->id, $call);
        } catch (OctoreviewException $e) {
            throw $failure(UploadException::STAGE_COMPLETE, $e);
        }
    }

    /**
     * @param array{author_id?: string, include_unpublished?: bool} $params
     *
     * @return list<Media>
     */
    public function list(string $reviewId, array $params = [], ?CallOptions $options = null): array
    {
        return $this->many('listReviewMedia', [$reviewId], $params, $options, static fn(array $data): Media => Media::fromArray($data));
    }

    /**
     * Reserves a media slot and returns where to PUT the bytes. `client_upload_id` makes the call
     * retry-safe; one is generated when absent, so a retried intent returns the same media.
     * `kind` is "image" or "video".
     *
     * @param array{client_upload_id?: string, position?: int, width?: int, height?: int, duration_seconds?: float} $fields
     */
    public function createUpload(
        string $reviewId,
        string $kind,
        string $contentType,
        int $sizeBytes,
        array $fields = [],
        ?CallOptions $options = null,
    ): MediaUpload {
        $fields += ['client_upload_id' => self::uploadId()];

        return $this->one('createMediaUpload', [$reviewId], [
            'kind' => $kind,
            'content_type' => $contentType,
            'size_bytes' => $sizeBytes,
        ] + $fields, $options, MediaUpload::fromArray(...));
    }

    /**
     * Confirms the PUT and returns the ready media (retry-safe; `complete_in_progress` and
     * `upload_changed` are retried).
     */
    public function complete(string $reviewId, string $mediaId, ?CallOptions $options = null): Media
    {
        return $this->one('completeMediaUpload', [$reviewId, $mediaId], [], $options, Media::fromArray(...));
    }

    public function delete(string $reviewId, string $mediaId, ?CallOptions $options = null): Media
    {
        return $this->one('deleteMedia', [$reviewId, $mediaId], [], $options, Media::fromArray(...));
    }

    /** Approves the media item, guarded by the review version the moderator saw. */
    public function approve(
        string $reviewId,
        string $mediaId,
        int $expectedVersion,
        int $expectedMediaVersion,
        ?string $reason = null,
        ?CallOptions $options = null,
    ): Media {
        $params = ['expected_version' => $expectedVersion, 'expected_media_version' => $expectedMediaVersion];
        if ($reason !== null) {
            $params['reason'] = $reason;
        }

        return $this->one('approveMedia', [$reviewId, $mediaId], $params, $options, Media::fromArray(...));
    }

    public function reject(string $reviewId, string $mediaId, ?string $reason = null, ?CallOptions $options = null): Media
    {
        return $this->one('rejectMedia', [$reviewId, $mediaId], $reason === null ? [] : ['reason' => $reason], $options, Media::fromArray(...));
    }

    /**
     * After a terminal PUT failure: another call with the same client upload id may have PUT and
     * completed this media item, and deleting it would invalidate that caller's result. Complete
     * is idempotent: it answers the ready media then (returned), or upload_not_found when no
     * bytes ever landed, the one case that frees the slot. Any other answer leaves the row to the
     * server's cleanup.
     */
    private function cleanUp(string $reviewId, string $mediaId, ?CallOptions $call): ?Media
    {
        try {
            $media = $this->complete($reviewId, $mediaId, $call);

            return $media->status === 'ready' ? $media : null;
        } catch (ApiException $e) {
            if ($e->errorCode === ErrorCode::UPLOAD_NOT_FOUND) {
                try {
                    $this->delete($reviewId, $mediaId, $call);
                } catch (OctoreviewException) {
                    // best effort: the server's cleanup frees the slot after expiry
                }
            }
        } catch (OctoreviewException) {
            // the server's cleanup owns an undecidable row
        }

        return null;
    }

    /** @return list<string> */
    private static function requestIds(?string $intentRequestId, ?\Throwable $e): array
    {
        $ids = [$intentRequestId, $e instanceof ApiException ? $e->requestId : null];

        return array_values(array_filter($ids, static fn(?string $id): bool => $id !== null && $id !== ''));
    }

    /** A random RFC 4122 version 4 UUID. */
    private static function uploadId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
