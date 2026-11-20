<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\Page;
use Octoverse\Octoreview\Model\Review;
use Octoverse\Octoreview\Model\Revision;

/**
 * Reviews: create, read, edit and moderate. The moderation calls that make content public
 * (publish, approveRevision) take the `version` and `mediaVersion` the moderator saw; a review
 * that changed since throws ApiException `review_changed` (ApiException::isReviewChanged()).
 */
final class ReviewsService extends Service
{
    /**
     * Without `author_id` or `include_unpublished` only published reviews are listed;
     * `include_unpublished` needs the reviews:moderate scope.
     *
     * @param array{entity_id?: string, entity_type?: string, external_id?: string, author_id?: string, include_unpublished?: bool, status?: string, rating?: int, has_media?: bool, is_verified_purchase?: bool, has_pending_revision?: bool, media_moderation_status?: string, sort?: string, limit?: int, offset?: int} $filters
     *
     * @return Page<Review>
     */
    public function list(array $filters = [], ?CallOptions $options = null): Page
    {
        return $this->page('listReviews', [], $filters, $options, static fn(array $data): Review => Review::fromArray($data));
    }

    /**
     * Creates a review of an entity named by `entity_id`, or by `entity_type` and `external_id`
     * (upserted). Not retried: after a lost response, list the author's reviews to learn whether
     * it exists (a repeat answers 409 `review_exists`).
     *
     * @param array{entity_id?: string, entity_type?: string, external_id?: string, title?: ?string, body?: ?string, metadata?: array<string, mixed>, author_metadata?: array<string, mixed>, source_ref?: ?string, is_verified_purchase?: bool} $fields
     */
    public function create(string $authorId, int $rating, array $fields, ?CallOptions $options = null): Review
    {
        return $this->one('createReview', [], ['author_id' => $authorId, 'rating' => $rating] + $fields, $options, Review::fromArray(...));
    }

    /**
     * Published reviews only, unless `author_id` (the author's own) or `include_unpublished`
     * (a moderator) is given.
     *
     * @param array{author_id?: string, include_unpublished?: bool} $params
     */
    public function get(string $reviewId, array $params = [], ?CallOptions $options = null): Review
    {
        return $this->one('getReview', [$reviewId], $params, $options, Review::fromArray(...));
    }

    /**
     * An edit. Under pre-moderation an edit of a published review becomes a pending revision.
     *
     * @param array{rating?: int, title?: ?string, body?: ?string, metadata?: array<string, mixed>, author_metadata?: array<string, mixed>, is_verified_purchase?: bool} $fields
     */
    public function update(string $reviewId, array $fields, ?CallOptions $options = null): Review
    {
        return $this->one('updateReview', [$reviewId], $fields, $options, Review::fromArray(...));
    }

    /** The author withdraws the review. */
    public function remove(string $reviewId, ?CallOptions $options = null): Review
    {
        return $this->one('removeReview', [$reviewId], [], $options, Review::fromArray(...));
    }

    /** Publishes the review exactly as the moderator saw it (the version guard). */
    public function publish(
        string $reviewId,
        int $expectedVersion,
        int $expectedMediaVersion,
        ?string $reason = null,
        ?CallOptions $options = null,
    ): Review {
        return $this->one('publishReview', [$reviewId], self::withReason([
            'expected_version' => $expectedVersion,
            'expected_media_version' => $expectedMediaVersion,
        ], $reason), $options, Review::fromArray(...));
    }

    public function reject(string $reviewId, ?string $reason = null, ?CallOptions $options = null): Review
    {
        return $this->one('rejectReview', [$reviewId], self::withReason([], $reason), $options, Review::fromArray(...));
    }

    public function hide(string $reviewId, ?string $reason = null, ?CallOptions $options = null): Review
    {
        return $this->one('hideReview', [$reviewId], self::withReason([], $reason), $options, Review::fromArray(...));
    }

    /** The pending edit of a published review; none throws ApiException `revision_not_found`. */
    public function getRevision(string $reviewId, ?CallOptions $options = null): Revision
    {
        return $this->one('getReviewRevision', [$reviewId], [], $options, Revision::fromArray(...));
    }

    /** Applies the pending edit, guarded by the version the moderator saw. */
    public function approveRevision(
        string $reviewId,
        string $revisionId,
        int $expectedVersion,
        int $expectedMediaVersion,
        ?CallOptions $options = null,
    ): Review {
        return $this->one('approveReviewRevision', [$reviewId], [
            'revision_id' => $revisionId,
            'expected_version' => $expectedVersion,
            'expected_media_version' => $expectedMediaVersion,
        ], $options, Review::fromArray(...));
    }

    public function rejectRevision(string $reviewId, string $revisionId, ?CallOptions $options = null): Review
    {
        return $this->one('rejectReviewRevision', [$reviewId], ['revision_id' => $revisionId], $options, Review::fromArray(...));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function withReason(array $params, ?string $reason): array
    {
        return $reason === null ? $params : $params + ['reason' => $reason];
    }
}
