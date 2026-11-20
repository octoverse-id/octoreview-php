<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** One erasure batch; call erase() again while `remaining` is above 0. */
final class EraseResult
{
    public function __construct(
        public readonly int $reviewsRedacted,
        public readonly int $pendingMediaDeletes,
        public readonly int $remaining,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::int($data, 'reviews_redacted'),
            Data::int($data, 'pending_media_deletes'),
            Data::int($data, 'remaining'),
            $meta,
        );
    }
}
