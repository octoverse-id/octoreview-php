<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\BatchRating;
use Octoverse\Octoreview\Model\EntityRating;
use Octoverse\Octoreview\Model\RatingSummary;
use Octoverse\Octoreview\Transport;

/** Rating summaries of published reviews. */
final class RatingsService extends Service
{
    public function __construct(Transport $transport, private readonly EntitiesService $entities)
    {
        parent::__construct($transport);
    }

    public function forEntity(string $entityId, ?CallOptions $options = null): EntityRating
    {
        return $this->one('getEntityRating', [$entityId], [], $options, EntityRating::fromArray(...));
    }

    /**
     * One summary per external id, in request order (a listing page); unknown ids answer zeros.
     *
     * @param list<string> $externalIds
     *
     * @return list<BatchRating>
     */
    public function query(string $entityType, array $externalIds, ?CallOptions $options = null): array
    {
        return $this->many(
            'queryRatings',
            [],
            ['entity_type' => $entityType, 'external_ids' => array_values($externalIds)],
            $options,
            static fn(array $data): BatchRating => BatchRating::fromArray($data),
        );
    }

    /**
     * The summary of {entityType, externalId} without knowing the entity id (the ratingsByKey
     * helper; it composes entities()->lookup). An entity that does not exist yet throws
     * ApiException `entity_not_found`.
     */
    public function get(string $entityType, string $externalId, ?CallOptions $options = null): RatingSummary
    {
        return $this->entities->lookup($entityType, $externalId, $options)->rating;
    }
}
