<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

/** Event types (the server's docs/events.md); the contract gate checks them against the vendored copy. */
final class EventType
{
    public const REVIEW_CREATED = 'review.created';
    public const REVIEW_UPDATED = 'review.updated';
    public const REVIEW_REVISION_SUBMITTED = 'review.revision_submitted';
    public const REVIEW_STATUS_CHANGED = 'review.status_changed';
    public const REVIEW_PUBLISHED = 'review.published';
    public const REVIEW_REMOVED = 'review.removed';
    public const MEDIA_READY = 'review_media.ready';
    public const MEDIA_INVALID = 'review_media.invalid';
    public const MEDIA_DELETED = 'review_media.deleted';
    public const MEDIA_MODERATED = 'review_media.moderated';
    public const ENTITY_RATING_CHANGED = 'entity.rating_changed';
    public const AUTHOR_ERASED = 'author.erased';

    /** The types whose payload is a ReviewEvent. */
    public const REVIEW_EVENTS = [
        self::REVIEW_CREATED, self::REVIEW_UPDATED, self::REVIEW_REVISION_SUBMITTED,
        self::REVIEW_STATUS_CHANGED, self::REVIEW_PUBLISHED, self::REVIEW_REMOVED,
    ];

    /** The types whose payload is a MediaEvent. */
    public const MEDIA_EVENTS = [self::MEDIA_READY, self::MEDIA_INVALID, self::MEDIA_DELETED, self::MEDIA_MODERATED];
}
