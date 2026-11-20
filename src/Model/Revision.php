<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** An edit to a published review waiting for moderation. */
final class Revision
{
    public function __construct(
        public readonly string $id,
        public readonly string $reviewId,
        public readonly int $rating,
        public readonly ?string $title,
        public readonly ?string $body,
        public readonly int $baseVersion,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::string($data, 'id'),
            Data::string($data, 'review_id'),
            Data::int($data, 'rating'),
            Data::nullableString($data, 'title'),
            Data::nullableString($data, 'body'),
            Data::int($data, 'base_version'),
            Data::time($data, 'created_at'),
            $meta,
        );
    }
}
