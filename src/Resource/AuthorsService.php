<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\EraseResult;

/** Author data rights. */
final class AuthorsService extends Service
{
    /**
     * Redacts one batch of an author's reviews and schedules their media for deletion. Call it
     * again while `remaining` is above 0 (retry-safe).
     */
    public function erase(string $authorId, ?CallOptions $options = null): EraseResult
    {
        return $this->one('eraseAuthor', [], ['author_id' => $authorId], $options, EraseResult::fromArray(...));
    }
}
