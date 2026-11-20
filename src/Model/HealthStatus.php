<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** A health probe's answer: live reports `status`; ready also reports `database` ("unavailable"/"down" when it is down). */
final class HealthStatus
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $database,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::string($data, 'status'),
            Data::nullableString($data, 'database'),
            $meta,
        );
    }
}
