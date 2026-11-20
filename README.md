# Octoreview PHP SDK

The official PHP client for [Octoreview](https://github.com/octoverse-id/octoreview), the
multi-tenant review and rating service with image and video review media.

```sh
composer require octoverse-id/octoreview-php
```

PHP 8.2 or newer; PSR-18, with Guzzle by default. No release yet: `0.1.0` ships with the
server's `1.0.0-rc.1` ([`docs/versioning.md`](docs/versioning.md)).

## Usage

```php
use Octoverse\Octoreview\Client;

$client = Client::fromEnv(); // OCTOREVIEW_BASE_URL, _API_KEY, _TENANT_ID, _APPLICATION_ID

$review = $client->reviews()->create(
    $currentUser->id, // from your own authentication, never from request input
    5,
    ['entity_type' => 'product', 'external_id' => 'sku-1', 'title' => 'Fits perfectly'],
);

$summary = $client->ratings()->get('product', 'sku-1');                 // one entity's stars
$ratings = $client->ratings()->query('product', ['sku-1', 'sku-2']);    // a listing page's stars
```

`new Client($baseUrl, $apiKey, $tenantId, $options)` builds a client explicitly. `ClientOptions`
sets defaults (`withApplication`, `withNamespace`, `withActor`, `withHttpClient`, `withRetry`),
and `CallOptions` overrides them per call (`forApplication`, `inNamespace`, `inGlobalScope`,
`includeGlobal`, `asActor`, `ifNoneMatch`, `withRequestId`). Both are immutable: every `with*`
returns a copy, so one client is safe to share.

```php
use Octoverse\Octoreview\{CallOptions, ClientOptions};

$client = new Client('https://reviews.example.com', $apiKey, 'mbizmarket',
    (new ClientOptions())->withApplication('storefront')->withNamespace('store', 'store-9'));
$page = $client->reviews()->list(['entity_type' => 'product', 'external_id' => 'sku-1', 'limit' => 20],
    CallOptions::create()->includeGlobal());
```

**Parameters** use the server's names (`entity_type`, `max_images`, ...). An unknown key, or a
required one missing, throws `InvalidArgumentException` before anything is sent. The application
comes from `CallOptions::forApplication()` or the client default, never from a parameter. In
`update` calls a key you pass as `null` is sent as `null` (a media limit then inherits); a key
you leave out is left unchanged.

**Render safely.** Review titles and bodies are user input: escape them when you render them
(`htmlspecialchars()`, or your template engine's auto-escaping).

**Moderation.** `reviews()->publish()`, `reviews()->approveRevision()` and `media()->approve()`
take the review's `version` and `mediaVersion` as the moderator saw them. A review that changed
since then throws `ApiException` with `isReviewChanged()`: reload it and show the new content.

## Methods

Every method of the server's [SDK surface](https://github.com/octoverse-id/octoreview/blob/main/docs/sdk-surface.yaml):
`health()`, `entities()`, `ratings()`, `settings()`, `reviews()`, `media()`, `authors()` and
`auditLogs()`. Single results are readonly models (`Entity`, `Review`, `Media`, ...) with a
`meta` (`etag`, `requestId`); lists return a `Page` (`items`, `pagination`, `meta`).

The upload helpers (`media()->uploadFile()`, `uploadBytes()`, `uploadStream()`) and webhook
verification arrive in the next PR; until then, upload with `media()->createUpload()`, a PUT of
the bytes to `$intent->upload->url` with exactly `$intent->upload->headers` (never your service
key), then `media()->complete()`.

## Errors

Every exception extends `Octoverse\Octoreview\Exception\OctoreviewException`:

| Exception | When |
| --- | --- |
| `ApiException` | the server answered an error: `status`, `errorCode` (an `ErrorCode` constant), `details`, `requestId`, `docUrl`, `retryable`, `retryAfter` |
| `TransportException` | the server could not be reached, or its answer could not be read |
| `NotModifiedException` | a conditional read (`CallOptions::ifNoneMatch($etag)`) found the resource unchanged |
| `MalformedResponseException` | a success response lacks a field this SDK needs |
| `ConfigurationException` | the client was built without a valid base URL, key or tenant |

Messages never contain the API key, a query string (signatures) or a media token.

## Retries

Each method's retry class comes from the server's `docs/sdk-surface.yaml`. Retries back off and
honor `Retry-After` up to `ClientOptions::withRetry()`'s bound (default 3 attempts, 30 s).

<!-- retry-table:start -->
| Retry class | Behavior | Methods |
| --- | --- | --- |
| `safe` | a read: retried on timeouts, 503 and 429, honoring Retry-After | `auditLogs()->list`, `entities()->get`, `entities()->list`, `entities()->lookup`, `health()->live`, `health()->ready`, `media()->list`, `ratings()->forEntity`, `ratings()->query`, `reviews()->getRevision`, `reviews()->get`, `reviews()->list`, `settings()->get` |
| `idempotent` | repeats to the same state: retried like a read | `authors()->erase`, `entities()->deactivate`, `entities()->update`, `entities()->upsert`, `media()->complete`, `media()->delete`, `media()->reject`, `settings()->put` |
| `keyed` | idempotent with its `client_upload_id`, which the SDK fills in: retried like a read | `media()->createUpload` |
| `unsafe` | never retried; after a lost response, read the resource to learn the outcome | `media()->approve`, `reviews()->approveRevision`, `reviews()->create`, `reviews()->hide`, `reviews()->publish`, `reviews()->rejectRevision`, `reviews()->reject`, `reviews()->remove`, `reviews()->update` |
<!-- retry-table:end -->

## Working on this repository

Read [`AGENTS.md`](AGENTS.md) first (humans too): it holds the rules, gates and development
pipeline. `make help` lists the targets; `make check` is the fast gate, and it includes the
offline contract gate against the vendored server contract
([`docs/architecture.md`](docs/architecture.md)).

## License

[Apache License 2.0](LICENSE).
