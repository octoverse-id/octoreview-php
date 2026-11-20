<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** One image or video of a review. `url` is null when the caller may not see it, or when signing failed (`urlUnavailable` "signing"). */
final class Media
{
    public function __construct(
        public readonly string $id,
        public readonly string $reviewId,
        public readonly string $kind,
        public readonly string $status,
        public readonly string $moderationStatus,
        public readonly string $contentType,
        public readonly int $sizeBytes,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?float $durationSeconds,
        public readonly int $position,
        public readonly ?\DateTimeImmutable $uploadExpiresAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $url,
        public readonly ?\DateTimeImmutable $urlExpiresAt,
        public readonly ?string $urlUnavailable,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Data::string($data, 'id'),
            Data::string($data, 'review_id'),
            Data::string($data, 'kind'),
            Data::string($data, 'status'),
            Data::string($data, 'moderation_status'),
            Data::string($data, 'content_type'),
            Data::int($data, 'size_bytes'),
            Data::nullableInt($data, 'width'),
            Data::nullableInt($data, 'height'),
            Data::nullableFloat($data, 'duration_seconds'),
            Data::int($data, 'position'),
            Data::nullableTime($data, 'upload_expires_at'),
            Data::time($data, 'created_at'),
            Data::nullableString($data, 'url'),
            Data::nullableTime($data, 'url_expires_at'),
            Data::nullableString($data, 'url_unavailable'),
            $meta,
        );
    }
}
