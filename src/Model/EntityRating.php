<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** An entity's rating summary. */
final class EntityRating
{
    public function __construct(
        public readonly string $entityId,
        public readonly int $ratingCount,
        public readonly ?float $ratingAverage,
        /** @var array<string, mixed> */
        public readonly array $histogram,
        /** @var array<string, mixed> */
        public readonly array $histogramPercent,
        public readonly int $verifiedReviewCount,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::string($data, 'entity_id'),
            Data::int($data, 'rating_count'),
            Data::nullableFloat($data, 'rating_average'),
            Data::map($data, 'histogram'),
            Data::map($data, 'histogram_percent'),
            Data::int($data, 'verified_review_count'),
            $meta,
        );
    }
}
