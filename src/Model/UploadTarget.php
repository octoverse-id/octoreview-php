<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

/** Where to PUT a media file: send exactly `headers` plus an explicit Content-Length, never the service key. */
final class UploadTarget
{
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        /** @var array<string, mixed> */
        public readonly array $headers,
        public readonly \DateTimeImmutable $expiresAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'method'),
            Data::string($data, 'url'),
            Data::map($data, 'headers'),
            Data::time($data, 'expires_at'),
        );
    }
}
