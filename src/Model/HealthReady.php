<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/**
 * The readiness probe's answer: `status` "ok" or "unavailable", `database` "ok" or "down". A down
 * database is an answer (HTTP 503), not an exception.
 */
final class HealthReady
{
    public function __construct(
        public readonly string $status,
        public readonly string $database,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(Data::string($data, 'status'), Data::string($data, 'database'), $meta);
    }
}
