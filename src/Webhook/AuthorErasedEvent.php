<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

use Octoverse\Octoreview\Model\Data;

/**
 * author.erased: delete every copy you hold of this author's reviews and personal data, and drop
 * older events about the author that arrive later.
 */
final class AuthorErasedEvent
{
    public function __construct(
        public readonly string $authorId,
        public readonly \DateTimeImmutable $erasedAt,
        public readonly int $reviewsRedacted,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'author_id'),
            Data::time($data, 'erased_at'),
            Data::int($data, 'reviews_redacted'),
        );
    }
}
