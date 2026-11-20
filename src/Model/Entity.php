<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** An external resource that can be reviewed, such as product/sku-1. */
final class Entity
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $applicationId,
        public readonly ?string $namespaceType,
        public readonly ?string $namespaceId,
        public readonly string $entityType,
        public readonly string $externalId,
        /** @var array<string, mixed> */
        public readonly array $metadata,
        public readonly bool $isActive,
        public readonly MediaPolicy $mediaPolicy,
        public readonly RatingSummary $rating,
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
            Data::string($data, 'entity_type'),
            Data::string($data, 'external_id'),
            Data::map($data, 'metadata'),
            Data::bool($data, 'is_active'),
            MediaPolicy::fromArray(Data::map($data, 'media_policy')),
            RatingSummary::fromArray(Data::map($data, 'rating')),
            Data::time($data, 'created_at'),
            Data::time($data, 'updated_at'),
            $meta,
        );
    }
}
