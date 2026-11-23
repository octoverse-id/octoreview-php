<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

use Octoverse\Octoreview\Model\Data;

/**
 * One delivered outbox event. `data` is the type's thin payload: decode it with review(),
 * media(), ratingChanged() or authorErased(). Deduplicate on `id` (delivery is at least once).
 */
final class Event
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly string $tenantId,
        public readonly ?string $applicationId,
        public readonly ?string $namespaceType,
        public readonly ?string $namespaceId,
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly ?string $requestId,
        public readonly array $data,
    ) {}

    /** @param array<string, mixed> $event */
    public static function fromArray(array $event): self
    {
        return new self(
            Data::string($event, 'id'),
            Data::string($event, 'type'),
            Data::time($event, 'occurred_at'),
            Data::string($event, 'tenant_id'),
            Data::nullableString($event, 'application_id'),
            Data::nullableString($event, 'namespace_type'),
            Data::nullableString($event, 'namespace_id'),
            Data::string($event, 'resource_type'),
            Data::string($event, 'resource_id'),
            Data::nullableString($event, 'request_id'),
            Data::map($event, 'data'),
        );
    }

    /** A review.* payload. */
    public function review(): ReviewEvent
    {
        $this->expect(EventType::REVIEW_EVENTS, ReviewEvent::class);

        return ReviewEvent::fromArray($this->data);
    }

    /** A review_media.* payload. */
    public function media(): MediaEvent
    {
        $this->expect(EventType::MEDIA_EVENTS, MediaEvent::class);

        return MediaEvent::fromArray($this->data);
    }

    /** An entity.rating_changed payload. */
    public function ratingChanged(): RatingChangedEvent
    {
        $this->expect([EventType::ENTITY_RATING_CHANGED], RatingChangedEvent::class);

        return RatingChangedEvent::fromArray($this->data);
    }

    /** An author.erased payload. */
    public function authorErased(): AuthorErasedEvent
    {
        $this->expect([EventType::AUTHOR_ERASED], AuthorErasedEvent::class);

        return AuthorErasedEvent::fromArray($this->data);
    }

    /** @param list<string> $types */
    private function expect(array $types, string $payload): void
    {
        if (!in_array($this->type, $types, true)) {
            throw new \LogicException(sprintf('octoreview: a %s event has no %s payload', $this->type, $payload));
        }
    }
}
