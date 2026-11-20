<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/**
 * The server could not be reached, or its answer could not be read. The HTTP client's own
 * exception is not chained: its message can carry the key or a signed URL, and PHP prints the
 * whole chain when an exception is cast to a string.
 */
final class TransportException extends OctoreviewException
{
    public function __construct(
        string $message,
        /** A connection-level failure (refused, reset, timed out): retried for retry-safe calls. */
        public readonly bool $networkFailure = false,
        /** The server's X-Request-ID, when it answered. */
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($requestId === null ? $message : "{$message} (request id {$requestId})");
    }
}
