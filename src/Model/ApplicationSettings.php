<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** An application's media policy defaults, moderation mode and storage quota. */
final class ApplicationSettings
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $applicationId,
        public readonly ?int $maxImages,
        public readonly ?int $maxVideos,
        public readonly ?int $maxImageBytes,
        public readonly ?int $maxVideoBytes,
        /** @var list<string>|null */
        public readonly ?array $allowedContentTypes,
        public readonly ?string $moderationMode,
        public readonly ?int $maxStorageBytes,
        public readonly int $storedBytes,
        public readonly int $reservedBytes,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::string($data, 'tenant_id'),
            Data::string($data, 'application_id'),
            Data::nullableInt($data, 'max_images'),
            Data::nullableInt($data, 'max_videos'),
            Data::nullableInt($data, 'max_image_bytes'),
            Data::nullableInt($data, 'max_video_bytes'),
            Data::nullableList($data, 'allowed_content_types'),
            Data::nullableString($data, 'moderation_mode'),
            Data::nullableInt($data, 'max_storage_bytes'),
            Data::int($data, 'stored_bytes'),
            Data::int($data, 'reserved_bytes'),
            $meta,
        );
    }
}
