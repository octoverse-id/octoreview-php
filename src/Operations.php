<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/**
 * Every REST operation, one row each. The contract gate checks the table against the vendored
 * docs/openapi.yaml (method, path, body fields) and docs/sdk-surface.yaml (PHP method, retry
 * class, required and optional parameters), in both directions.
 *
 * @internal
 */
final class Operations
{
    /** @var array<string, Operation>|null */
    private static ?array $all = null;

    /** @return array<string, Operation> keyed by operation id */
    public static function all(): array
    {
        return self::$all ??= [
            'getHealthLive' => new Operation('getHealthLive', 'GET', '/health/live', 'safe', 'health()->live', [], [], []),
            'getHealthReady' => new Operation('getHealthReady', 'GET', '/health/ready', 'safe', 'health()->ready', [], [], []),
            'listEntities' => new Operation('listEntities', 'GET', '/entities', 'safe', 'entities()->list', [], ['application_id', 'include_global', 'entity_type', 'external_id', 'is_active', 'limit', 'offset'], []),
            'upsertEntity' => new Operation('upsertEntity', 'PUT', '/entities', 'idempotent', 'entities()->upsert', ['application_id', 'entity_type', 'external_id'], ['metadata', 'max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes'], ['application_id', 'entity_type', 'external_id', 'max_image_bytes', 'max_images', 'max_video_bytes', 'max_videos', 'metadata']),
            'lookupEntity' => new Operation('lookupEntity', 'GET', '/entities/lookup', 'safe', 'entities()->lookup', ['application_id', 'entity_type', 'external_id'], ['include_global'], []),
            'getEntity' => new Operation('getEntity', 'GET', '/entities/{entity_id}', 'safe', 'entities()->get', ['entity_id'], ['application_id', 'include_global'], []),
            'updateEntity' => new Operation('updateEntity', 'PATCH', '/entities/{entity_id}', 'idempotent', 'entities()->update', ['entity_id'], ['application_id', 'metadata', 'max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes', 'is_active'], ['is_active', 'max_image_bytes', 'max_images', 'max_video_bytes', 'max_videos', 'metadata']),
            'deactivateEntity' => new Operation('deactivateEntity', 'DELETE', '/entities/{entity_id}', 'idempotent', 'entities()->deactivate', ['entity_id'], ['application_id'], []),
            'getEntityRating' => new Operation('getEntityRating', 'GET', '/entities/{entity_id}/rating', 'safe', 'ratings()->forEntity', ['entity_id'], ['application_id', 'include_global'], []),
            'queryRatings' => new Operation('queryRatings', 'POST', '/entities/ratings/query', 'safe', 'ratings()->query', ['application_id', 'entity_type', 'external_ids'], ['include_global'], ['application_id', 'entity_type', 'external_ids']),
            'getApplicationSettings' => new Operation('getApplicationSettings', 'GET', '/applications/{application_id}/settings', 'safe', 'settings()->get', ['application_id'], [], []),
            'putApplicationSettings' => new Operation('putApplicationSettings', 'PUT', '/applications/{application_id}/settings', 'idempotent', 'settings()->put', ['application_id', 'max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes', 'allowed_content_types', 'moderation_mode', 'max_storage_bytes'], [], ['allowed_content_types', 'max_image_bytes', 'max_images', 'max_storage_bytes', 'max_video_bytes', 'max_videos', 'moderation_mode']),
            'listReviews' => new Operation('listReviews', 'GET', '/reviews', 'safe', 'reviews()->list', [], ['application_id', 'include_global', 'entity_id', 'entity_type', 'external_id', 'author_id', 'include_unpublished', 'status', 'rating', 'has_media', 'is_verified_purchase', 'has_pending_revision', 'media_moderation_status', 'sort', 'limit', 'offset'], []),
            'createReview' => new Operation('createReview', 'POST', '/reviews', 'unsafe', 'reviews()->create', ['application_id', 'author_id', 'rating'], ['entity_id', 'entity_type', 'external_id', 'title', 'body', 'metadata', 'author_metadata', 'source_ref', 'is_verified_purchase'], ['application_id', 'author_id', 'author_metadata', 'body', 'entity_id', 'entity_type', 'external_id', 'is_verified_purchase', 'metadata', 'rating', 'source_ref', 'title']),
            'getReview' => new Operation('getReview', 'GET', '/reviews/{review_id}', 'safe', 'reviews()->get', ['review_id'], ['application_id', 'include_global', 'author_id', 'include_unpublished'], []),
            'updateReview' => new Operation('updateReview', 'PATCH', '/reviews/{review_id}', 'unsafe', 'reviews()->update', ['review_id'], ['application_id', 'rating', 'title', 'body', 'metadata', 'author_metadata', 'is_verified_purchase'], ['author_metadata', 'body', 'is_verified_purchase', 'metadata', 'rating', 'title']),
            'removeReview' => new Operation('removeReview', 'DELETE', '/reviews/{review_id}', 'unsafe', 'reviews()->remove', ['review_id'], ['application_id'], []),
            'publishReview' => new Operation('publishReview', 'POST', '/reviews/{review_id}/publish', 'unsafe', 'reviews()->publish', ['review_id', 'expected_version', 'expected_media_version'], ['application_id', 'reason'], ['expected_media_version', 'expected_version', 'reason']),
            'rejectReview' => new Operation('rejectReview', 'POST', '/reviews/{review_id}/reject', 'unsafe', 'reviews()->reject', ['review_id'], ['application_id', 'reason'], ['reason']),
            'hideReview' => new Operation('hideReview', 'POST', '/reviews/{review_id}/hide', 'unsafe', 'reviews()->hide', ['review_id'], ['application_id', 'reason'], ['reason']),
            'getReviewRevision' => new Operation('getReviewRevision', 'GET', '/reviews/{review_id}/revision', 'safe', 'reviews()->getRevision', ['review_id'], ['application_id'], []),
            'approveReviewRevision' => new Operation('approveReviewRevision', 'POST', '/reviews/{review_id}/revision/approve', 'unsafe', 'reviews()->approveRevision', ['review_id', 'revision_id', 'expected_version', 'expected_media_version'], ['application_id'], ['expected_media_version', 'expected_version', 'revision_id']),
            'rejectReviewRevision' => new Operation('rejectReviewRevision', 'POST', '/reviews/{review_id}/revision/reject', 'unsafe', 'reviews()->rejectRevision', ['review_id', 'revision_id'], ['application_id'], ['revision_id']),
            'listReviewMedia' => new Operation('listReviewMedia', 'GET', '/reviews/{review_id}/media', 'safe', 'media()->list', ['review_id'], ['application_id', 'include_global', 'author_id', 'include_unpublished', 'limit', 'offset'], []),
            'createMediaUpload' => new Operation('createMediaUpload', 'POST', '/reviews/{review_id}/media', 'keyed', 'media()->createUpload', ['review_id', 'kind', 'content_type', 'size_bytes'], ['application_id', 'client_upload_id', 'position', 'width', 'height', 'duration_seconds'], ['client_upload_id', 'content_type', 'duration_seconds', 'height', 'kind', 'position', 'size_bytes', 'width']),
            'completeMediaUpload' => new Operation('completeMediaUpload', 'POST', '/reviews/{review_id}/media/{media_id}/complete', 'idempotent', 'media()->complete', ['review_id', 'media_id'], ['application_id'], []),
            'deleteMedia' => new Operation('deleteMedia', 'DELETE', '/reviews/{review_id}/media/{media_id}', 'idempotent', 'media()->delete', ['review_id', 'media_id'], ['application_id'], []),
            'approveMedia' => new Operation('approveMedia', 'POST', '/reviews/{review_id}/media/{media_id}/approve', 'unsafe', 'media()->approve', ['review_id', 'media_id', 'expected_version', 'expected_media_version'], ['application_id', 'reason'], ['expected_media_version', 'expected_version', 'reason']),
            'rejectMedia' => new Operation('rejectMedia', 'POST', '/reviews/{review_id}/media/{media_id}/reject', 'idempotent', 'media()->reject', ['review_id', 'media_id'], ['application_id', 'reason'], ['reason']),
            'eraseAuthor' => new Operation('eraseAuthor', 'POST', '/authors/erase', 'idempotent', 'authors()->erase', ['application_id', 'author_id'], [], ['application_id', 'author_id']),
            'listAuditLogs' => new Operation('listAuditLogs', 'GET', '/audit-logs', 'safe', 'auditLogs()->list', [], ['application_id', 'include_global', 'action', 'aggregate_type', 'aggregate_id', 'actor_id', 'limit', 'offset'], []),
        ];
    }

    public static function get(string $id): Operation
    {
        return self::all()[$id];
    }
}
