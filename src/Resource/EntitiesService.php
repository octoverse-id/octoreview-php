<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\Entity;
use Octoverse\Octoreview\Model\Page;

/** Reviewable entities: an external resource such as product/sku-1, with its media policy. */
final class EntitiesService extends Service
{
    /**
     * @param array{entity_type?: string, external_id?: string, is_active?: bool, limit?: int, offset?: int} $filters
     *
     * @return Page<Entity>
     */
    public function list(array $filters = [], ?CallOptions $options = null): Page
    {
        return $this->page('listEntities', [], $filters, $options, static fn(array $data): Entity => Entity::fromArray($data));
    }

    /**
     * Creates the entity or updates it in place (retry-safe). `$fields` sets metadata and the media
     * policy overrides (`max_images`, `max_videos`, `max_image_bytes`, `max_video_bytes`; null inherits).
     *
     * @param array{metadata?: array<string, mixed>, max_images?: ?int, max_videos?: ?int, max_image_bytes?: ?int, max_video_bytes?: ?int} $fields
     */
    public function upsert(string $entityType, string $externalId, array $fields = [], ?CallOptions $options = null): Entity
    {
        return $this->one(
            'upsertEntity',
            [],
            ['entity_type' => $entityType, 'external_id' => $externalId] + $fields,
            $options,
            Entity::fromArray(...),
        );
    }

    /** Finds an entity by its key; a missing one throws ApiException `entity_not_found`. */
    public function lookup(string $entityType, string $externalId, ?CallOptions $options = null): Entity
    {
        return $this->one('lookupEntity', [], ['entity_type' => $entityType, 'external_id' => $externalId], $options, Entity::fromArray(...));
    }

    public function get(string $entityId, ?CallOptions $options = null): Entity
    {
        return $this->one('getEntity', [$entityId], [], $options, Entity::fromArray(...));
    }

    /**
     * Changes the given fields only; a null media policy value inherits.
     *
     * @param array{metadata?: array<string, mixed>, max_images?: ?int, max_videos?: ?int, max_image_bytes?: ?int, max_video_bytes?: ?int, is_active?: bool} $fields
     */
    public function update(string $entityId, array $fields, ?CallOptions $options = null): Entity
    {
        return $this->one('updateEntity', [$entityId], $fields, $options, Entity::fromArray(...));
    }

    /** Stops new reviews of the entity; existing ones stay readable. */
    public function deactivate(string $entityId, ?CallOptions $options = null): Entity
    {
        return $this->one('deactivateEntity', [$entityId], [], $options, Entity::fromArray(...));
    }
}
