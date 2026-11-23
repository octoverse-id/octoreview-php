<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

use Octoverse\Octoreview\Redactor;

/**
 * A failed uploadFile, uploadBytes or uploadStream. It names the stage that failed and the ids to
 * look the upload up by; it never contains the signed upload URL. getPrevious() is the cause.
 */
final class UploadException extends OctoreviewException
{
    public const STAGE_INTENT = 'intent';
    public const STAGE_PUT = 'put';
    public const STAGE_COMPLETE = 'complete';

    /** @param list<string> $requestIds */
    public function __construct(
        /** One of the STAGE_* constants. */
        public readonly string $stage,
        public readonly string $reviewId,
        public readonly ?string $mediaId = null,
        public readonly ?\DateTimeImmutable $uploadExpiresAt = null,
        /** The server request ids of the calls this upload made, oldest first. */
        public readonly array $requestIds = [],
        /** The storage provider's error code for a failed PUT (S3's XML <Code>, or the local backend's). */
        public readonly ?string $providerCode = null,
        /** What to change, for a known provider code. */
        public readonly ?string $fix = null,
        ?\Throwable $previous = null,
        ?string $reason = null,
    ) {
        $message = sprintf('octoreview: upload failed at %s (review %s', $stage, $reviewId);
        if ($mediaId !== null) {
            $message .= ", media {$mediaId}";
        }
        $message .= ')';
        if ($providerCode !== null) {
            $message .= ": {$providerCode}";
        }
        if ($fix !== null) {
            $message .= "; {$fix}";
        }
        $cause = $reason ?? $previous?->getMessage();
        if ($cause !== null && $cause !== '') {
            $message .= ': ' . Redactor::text($cause);
        }
        parent::__construct($message, 0, $previous);
    }
}
