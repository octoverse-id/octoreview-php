<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\Media;
use Octoverse\Octoreview\Model\MediaUpload;

/**
 * Review images and videos. The upload is three steps: createUpload (the intent), a PUT of the
 * bytes to `upload->url` with exactly `upload->headers`, then complete.
 */
final class MediaService extends Service
{
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

    /** A random RFC 4122 version 4 UUID. */
    private static function uploadId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
