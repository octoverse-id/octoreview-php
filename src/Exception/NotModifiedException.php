<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/** A conditional read (CallOptions::ifNoneMatch) found the resource unchanged (HTTP 304). */
final class NotModifiedException extends OctoreviewException
{
    public function __construct(
        string $message,
        /** The server's X-Request-ID of the 304 response. */
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($requestId === null ? $message : "{$message} (request id {$requestId})");
    }
}
