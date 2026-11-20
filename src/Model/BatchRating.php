<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

/** One external id's summary in a ratings query; `entityId` is null when the entity does not exist yet. */
final class BatchRating
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $entityId,
        public readonly ?string $namespaceType,
        public readonly ?string $namespaceId,
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
            Data::string($data, 'external_id'),
            Data::nullableString($data, 'entity_id'),
            Data::nullableString($data, 'namespace_type'),
            Data::nullableString($data, 'namespace_id'),
            Data::int($data, 'rating_count'),
            Data::nullableFloat($data, 'rating_average'),
            Data::map($data, 'histogram'),
            Data::map($data, 'histogram_percent'),
            Data::int($data, 'verified_review_count'),
        );
    }
}
