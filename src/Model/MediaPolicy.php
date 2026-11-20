<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

/** An entity's media overrides; null inherits the application, then the server. */
final class MediaPolicy
{
    public function __construct(
        public readonly ?int $maxImages,
        public readonly ?int $maxVideos,
        public readonly ?int $maxImageBytes,
        public readonly ?int $maxVideoBytes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::nullableInt($data, 'max_images'),
            Data::nullableInt($data, 'max_videos'),
            Data::nullableInt($data, 'max_image_bytes'),
            Data::nullableInt($data, 'max_video_bytes'),
        );
    }
}
