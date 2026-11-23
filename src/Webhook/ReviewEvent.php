<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

use Octoverse\Octoreview\Model\Data;

/**
 * The payload of every review.* event. Compare `version` and `mediaVersion` with what you stored
 * before applying it: events have no global order.
 */
final class ReviewEvent
{
    public function __construct(
        public readonly string $reviewId,
        public readonly string $entityId,
        public readonly string $entityType,
        public readonly string $externalId,
        public readonly string $status,
        public readonly ?string $previousStatus,
        public readonly int $rating,
        public readonly bool $isVerifiedPurchase,
        public readonly int $version,
        public readonly int $mediaVersion,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'review_id'),
            Data::string($data, 'entity_id'),
            Data::string($data, 'entity_type'),
            Data::string($data, 'external_id'),
            Data::string($data, 'status'),
            Data::nullableString($data, 'previous_status'),
            Data::int($data, 'rating'),
            Data::bool($data, 'is_verified_purchase'),
            Data::int($data, 'version'),
            Data::int($data, 'media_version'),
        );
    }
}
