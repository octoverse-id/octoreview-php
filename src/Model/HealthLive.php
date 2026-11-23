<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** The liveness probe's answer: `status` is "ok" while the process serves. */
final class HealthLive
{
    public function __construct(
        public readonly string $status,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(Data::string($data, 'status'), $meta);
    }
}
