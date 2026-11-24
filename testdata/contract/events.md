# Events

Octoreview records an outbox event in the same transaction as every change a consumer may care
about, then delivers it (spec section 10). This file lists every event type the server emits;
`make openapi-check` fails when the server's `EVENT_TYPES` registry and the sections below differ,
and a test compares each documented field list with real payloads.

## Envelope

Every delivered event is one JSON document:

```json
{
  "id": "8d0c5a2e-7f0a-4b8e-9d55-2f4f8d6f3a10",
  "type": "review.published",
  "occurred_at": "2026-11-19T10:15:00.123456+00:00",
  "tenant_id": "example",
  "application_id": "marketplace",
  "namespace_type": null,
  "namespace_id": null,
  "resource_type": "review",
  "resource_id": "1f9e7c5a-0b2d-4c8e-a6f1-3d5b7e9c1a20",
  "request_id": "req_6f1c0b0e2f4d4a3c9a1b2c3d4e5f6a7b",
  "data": {"review_id": "1f9e7c5a-0b2d-4c8e-a6f1-3d5b7e9c1a20", "...": "..."}
}
```

- **Thin payloads.** `data` carries ids, status, rating, counts and versions. It never carries a
  title, body, `metadata`, `author_metadata`, `author_id` or `source_ref` (`author.erased` is the
  one exception for `author_id`). Fetch details over the API.
- **At least once.** A consumer may see the same event twice; deduplicate on `id`.
- **No global order.** Order by `occurred_at` per resource, and compare `version` and
  `media_version` with what you stored before applying a review event.
- **Namespaces.** A namespaced event is delivered like any other, with its namespace in the
  envelope.

## Webhook delivery

With `OCTOREVIEW_OUTBOX_TRANSPORT=webhook` the server POSTs each envelope to
`OCTOREVIEW_WEBHOOK_URL` with:

| Header | Value |
| --- | --- |
| `X-Octoreview-Event` | the event type |
| `X-Octoreview-Delivery` | the event id |
| `X-Octoreview-Signature` | `t=<unix seconds>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>` |

Verify before parsing: recompute the HMAC over `t`, a dot and the raw body bytes with
`OCTOREVIEW_WEBHOOK_SECRET`, compare in constant time, and reject a `t` more than 300 seconds
from your clock. Every SDK ships `webhooks.verify(body, header, secret, tolerance = 300 s)` and
typed event decoding. Answer 2xx once the event is stored; anything else is retried with backoff
and ends in the dead-letter queue.

## Emission rules

- `review.status_changed` fires on every status transition. `review.published` and
  `review.removed` fire in addition when the review enters that status.
- Erasure is the exception: it moves the author's reviews to `removed` without review events and
  emits one `author.erased` per batch instead (and `entity.rating_changed` where the aggregate
  moved).
- A review created under auto-publish emits `review.created` and `review.published`.
- `entity.rating_changed` fires whenever the published aggregate changed.
- Media events fire on every media state change the API can observe.

## Review events

Review payload fields, shared by every review event:

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | `pending`, `published`, `rejected`, `hidden` or `removed` |
| `rating` | integer | 1 to 5 |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | content and status version |
| `media_version` | integer | media state version |

### review.created

A review was created, in `pending` or `published` status.

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | the status it was created in |
| `rating` | integer | 1 to 5 |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | content and status version |
| `media_version` | integer | media state version |

### review.updated

The review's content changed: a direct edit, or an approved revision.

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | the current status |
| `rating` | integer | the new rating |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | the new version |
| `media_version` | integer | media state version |

### review.revision_submitted

An edit of a published review under pre-moderation is waiting for a moderator. The published
content is unchanged until the revision is approved.

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | the current status |
| `rating` | integer | the published rating, not the revision's |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | the current version |
| `media_version` | integer | media state version |

### review.status_changed

Fires on every status transition.

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | the new status |
| `previous_status` | string | the status it left |
| `rating` | integer | 1 to 5 |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | the new version |
| `media_version` | integer | media state version |

### review.published

The review entered `published`. Fires in addition to `review.status_changed`, or with
`review.created` for a review published at creation.

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | `published` |
| `previous_status` | string or null | the status it left; null when published at creation |
| `rating` | integer | 1 to 5 |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | the new version |
| `media_version` | integer | media state version |

### review.removed

The review entered `removed` through `DELETE /reviews/{review_id}`. Fires in addition to
`review.status_changed`. Erasure removes reviews without this event (see `author.erased`).

| Field | Type | Meaning |
| --- | --- | --- |
| `review_id` | uuid | the review |
| `entity_id` | uuid | its entity |
| `entity_type` | string | the entity's type |
| `external_id` | string | the entity's external id |
| `status` | string | `removed` |
| `previous_status` | string | the status it left |
| `rating` | integer | 1 to 5 |
| `is_verified_purchase` | boolean | attested purchase |
| `version` | integer | the new version |
| `media_version` | integer | media state version |

## Media events

### review_media.ready

An upload completed and passed validation.

| Field | Type | Meaning |
| --- | --- | --- |
| `media_id` | uuid | the media item |
| `review_id` | uuid | its review |
| `entity_id` | uuid | the review's entity |
| `kind` | string | `image` or `video` |
| `status` | string | `ready` |
| `moderation_status` | string | `none`, or `pending` under pre-moderation of a published review |

### review_media.invalid

`complete` refused the uploaded file; the slot is free again.

| Field | Type | Meaning |
| --- | --- | --- |
| `media_id` | uuid | the media item |
| `review_id` | uuid | its review |
| `entity_id` | uuid | the review's entity |
| `kind` | string | `image` or `video` |
| `status` | string | `invalid` |
| `moderation_status` | string | `none` |
| `reason` | string | `size_mismatch`, `type_mismatch`, `type_not_allowed`, `too_large`, `over_limit` or `over_quota` |

### review_media.deleted

The media object was deleted from storage; the row stays as `deleted`.

| Field | Type | Meaning |
| --- | --- | --- |
| `media_id` | uuid | the media item |
| `review_id` | uuid | its review |
| `entity_id` | uuid | the review's entity |
| `kind` | string | `image` or `video` |
| `status` | string | `deleted` |
| `moderation_status` | string | the last moderation status |

### review_media.moderated

A moderator approved or rejected the media item.

| Field | Type | Meaning |
| --- | --- | --- |
| `media_id` | uuid | the media item |
| `review_id` | uuid | its review |
| `entity_id` | uuid | the review's entity |
| `kind` | string | `image` or `video` |
| `status` | string | `ready` |
| `moderation_status` | string | `approved` or `rejected` |

## Entity events

### entity.rating_changed

The entity's published aggregate changed. Replace your stored summary with this one.

| Field | Type | Meaning |
| --- | --- | --- |
| `entity_id` | uuid | the entity |
| `entity_type` | string | its type |
| `external_id` | string | its external id |
| `rating_count` | integer | published reviews |
| `rating_average` | number or null | two decimals; null when the count is 0 |
| `histogram` | object | `{"1": n, ..., "5": n}` |
| `verified_review_count` | integer | published verified reviews |

## Privacy events

### author.erased

A batch of the author's reviews was redacted (`POST /authors/erase`). One event per batch;
`erased_at` is the moment of that batch.

| Field | Type | Meaning |
| --- | --- | --- |
| `author_id` | string | the raw author id, the one place an event carries it |
| `erased_at` | string | ISO 8601 timestamp |
| `reviews_redacted` | integer | reviews redacted in this batch |

**Consumers must purge.** Delete every copy you hold of this author's reviews and personal data,
and drop any event about this author whose `occurred_at` is older than `erased_at`: it may arrive
late, after the erasure. The outbox row of `author.erased` is deleted as soon as it is delivered.
