<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

/** An entity's published-review aggregate. `ratingAverage` has two decimals; null when the count is 0. */
final class RatingSummary
{
    public function __construct(
        public readonly int $ratingCount,
        public readonly ?float $ratingAverage,
        /** @var array<string, mixed> */
        public readonly array $histogram,
        /** @var array<string, mixed> */
        public readonly array $histogramPercent,
        public readonly int $verifiedReviewCount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::int($data, 'rating_count'),
            Data::nullableFloat($data, 'rating_average'),
            Data::map($data, 'histogram'),
            Data::map($data, 'histogram_percent'),
            Data::int($data, 'verified_review_count'),
        );
    }
}
