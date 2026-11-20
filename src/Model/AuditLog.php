<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

/** One recorded change. */
final class AuditLog
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly ?string $applicationId,
        public readonly ?string $namespaceType,
        public readonly ?string $namespaceId,
        public readonly string $action,
        public readonly string $aggregateType,
        public readonly string $aggregateId,
        public readonly ?string $actorId,
        public readonly ?string $requestId,
        public readonly string $operationId,
        /** @var array<string, mixed> */
        public readonly array $changes,
        /** @var array<string, mixed> */
        public readonly array $metadata,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'id'),
            Data::string($data, 'tenant_id'),
            Data::nullableString($data, 'application_id'),
            Data::nullableString($data, 'namespace_type'),
            Data::nullableString($data, 'namespace_id'),
            Data::string($data, 'action'),
            Data::string($data, 'aggregate_type'),
            Data::string($data, 'aggregate_id'),
            Data::nullableString($data, 'actor_id'),
            Data::nullableString($data, 'request_id'),
            Data::string($data, 'operation_id'),
            Data::map($data, 'changes'),
            Data::map($data, 'metadata'),
            Data::time($data, 'created_at'),
        );
    }
}
