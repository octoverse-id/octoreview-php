<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/** What a result knows about the response that produced it. */
final class ResponseMeta
{
    public function __construct(
        /** Pass back with CallOptions::ifNoneMatch() for a conditional read. */
        public readonly ?string $etag = null,
        /** The server's X-Request-ID, for its logs. */
        public readonly ?string $requestId = null,
    ) {}
}
