<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/**
 * An error response: the octoreview error envelope plus the HTTP status. `errorCode` is one of
 * the ErrorCode constants; `details` carries the code-specific fields docs/errors.md lists.
 */
final class ApiException extends OctoreviewException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly ?string $requestId = null,
        public readonly ?string $docUrl = null,
        public readonly bool $retryable = false,
        /** The server's Retry-After in seconds, when it sent one. */
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct(sprintf(
            'octoreview: %s (HTTP %d, request id %s): %s',
            $errorCode,
            $status,
            $requestId ?? 'none',
            $message,
        ));
    }

    /** The version guard refused a moderation call: reload the review and show the new content. */
    public function isReviewChanged(): bool
    {
        return $this->errorCode === 'review_changed';
    }
}
