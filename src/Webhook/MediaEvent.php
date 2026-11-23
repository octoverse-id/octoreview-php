<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

use Octoverse\Octoreview\Model\Data;

/** The payload of every review_media.* event; `reason` is set on review_media.invalid. */
final class MediaEvent
{
    public function __construct(
        public readonly string $mediaId,
        public readonly string $reviewId,
        public readonly string $entityId,
        public readonly string $kind,
        public readonly string $status,
        public readonly string $moderationStatus,
        public readonly ?string $reason,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'media_id'),
            Data::string($data, 'review_id'),
            Data::string($data, 'entity_id'),
            Data::string($data, 'kind'),
            Data::string($data, 'status'),
            Data::string($data, 'moderation_status'),
            Data::nullableString($data, 'reason'),
        );
    }
}
