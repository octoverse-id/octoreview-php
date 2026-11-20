<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/** A success response without the data envelope, or lacking a field this SDK needs. */
final class MalformedResponseException extends OctoreviewException
{
    public function __construct(
        string $message,
        /** The server's X-Request-ID, to find the response in its logs. */
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($requestId === null ? $message : "{$message} (request id {$requestId})");
    }
}
