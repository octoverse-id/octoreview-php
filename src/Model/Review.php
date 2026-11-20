<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** One review of an entity. Pass `version` and `mediaVersion` as the moderator saw them to publish(), approveRevision() and media()->approve(). */
final class Review
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $applicationId,
        public readonly ?string $namespaceType,
        public readonly ?string $namespaceId,
        public readonly string $entityId,
        public readonly string $entityType,
        public readonly string $externalId,
        public readonly string $authorId,
        /** @var array<string, mixed> */
        public readonly array $authorMetadata,
        public readonly int $rating,
        public readonly ?string $title,
        public readonly ?string $body,
        public readonly string $status,
        public readonly ?string $statusReason,
        /** @var array<string, mixed> */
        public readonly array $metadata,
        public readonly ?string $sourceRef,
        public readonly bool $isVerifiedPurchase,
        public readonly int $version,
        public readonly int $mediaVersion,
        /** @var list<Media> */
        public readonly array $media,
        public readonly bool $mediaTruncated,
        public readonly ?\DateTimeImmutable $publishedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::string($data, 'id'),
            Data::string($data, 'tenant_id'),
            Data::string($data, 'application_id'),
            Data::nullableString($data, 'namespace_type'),
            Data::nullableString($data, 'namespace_id'),
            Data::string($data, 'entity_id'),
            Data::string($data, 'entity_type'),
            Data::string($data, 'external_id'),
            Data::string($data, 'author_id'),
            Data::map($data, 'author_metadata'),
            Data::int($data, 'rating'),
            Data::nullableString($data, 'title'),
            Data::nullableString($data, 'body'),
            Data::string($data, 'status'),
            Data::nullableString($data, 'status_reason'),
            Data::map($data, 'metadata'),
            Data::nullableString($data, 'source_ref'),
            Data::bool($data, 'is_verified_purchase'),
            Data::int($data, 'version'),
            Data::int($data, 'media_version'),
            array_map(static fn(array $item): Media => Media::fromArray($item), Data::list($data, 'media')),
            Data::bool($data, 'media_truncated'),
            Data::nullableTime($data, 'published_at'),
            Data::time($data, 'created_at'),
            Data::time($data, 'updated_at'),
            $meta,
        );
    }
}
