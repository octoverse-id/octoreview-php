<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

use Octoverse\Octoreview\Model\Data;

/** entity.rating_changed: replace your stored summary with it. */
final class RatingChangedEvent
{
    /** @param array<string, mixed> $histogram star => count */
    public function __construct(
        public readonly string $entityId,
        public readonly string $entityType,
        public readonly string $externalId,
        public readonly int $ratingCount,
        public readonly ?float $ratingAverage,
        public readonly array $histogram,
        public readonly int $verifiedReviewCount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'entity_id'),
            Data::string($data, 'entity_type'),
            Data::string($data, 'external_id'),
            Data::int($data, 'rating_count'),
            Data::nullableFloat($data, 'rating_average'),
            Data::map($data, 'histogram'),
            Data::int($data, 'verified_review_count'),
        );
    }
}
