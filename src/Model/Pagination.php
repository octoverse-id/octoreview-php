<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

/** A list's limit/offset window and the total count. */
final class Pagination
{
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly int $count,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::int($data, 'limit'),
            Data::int($data, 'offset'),
            Data::int($data, 'count'),
        );
    }
}
