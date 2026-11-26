# Octoreview PHP SDK

The official PHP client for [Octoreview](https://github.com/octoverse-id/octoreview), the
multi-tenant review and rating service with image and video review media.

```sh
composer require octoverse-id/octoreview-php
```

PHP 8.2 or newer; PSR-18, with Guzzle by default. `0.1.0` works with the server's
`1.0.0`, `1.0.0-rc.3` and `1.0.0-rc.4` ([`docs/versioning.md`](docs/versioning.md)).

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

$client = new Client('https://reviews.example.com', $apiKey, 'example',
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

## Uploads

```php
use Octoverse\Octoreview\UploadOptions;

$media = $client->media()->uploadFile($review->id, '/path/photo.jpg');   // type and size from the file
$media = $client->media()->uploadBytes($review->id, $data, 'image/png');
$media = $client->media()->uploadStream($review->id, $stream, 'video/mp4', $size,
    UploadOptions::create()->withProgress(fn(int $sent, int $total) => printf("%d/%d\n", $sent, $total)));
```

Each runs intent, PUT and complete, and returns the ready media.
- **The PUT** goes to the signed `upload->url` with only the headers the server returned and an
  explicit `Content-Length`, through a separate PSR-18 client (`ClientOptions::withUploadHttpClient()`).
  It never sends the service key, tenant or namespace headers, follows no redirects, and has no
  total timeout (a stalled transfer fails).
- **Retries:** a failed PUT is retried on the same URL until it expires, rewinding the stream
  (which must be seekable). `complete` retries on 503, `complete_in_progress` and `upload_changed`.
- **Failures** throw `UploadException` with `stage` (`intent`, `put` or `complete`), the media and
  review ids, `uploadExpiresAt`, `requestIds` and, for a failed PUT, the storage provider's
  `providerCode` and a `fix`; never the signed URL. A terminal PUT failure frees the slot unless
  another call with the same client upload id completed the upload.

Browsers can upload directly: create the intent with `media()->createUpload()` on your server,
hand `upload->url` and `upload->headers` to the browser, then call `media()->complete()`.

## Webhooks

```php
use Octoverse\Octoreview\Exception\InvalidSignatureException;
use Octoverse\Octoreview\Webhook\{EventType, Webhook};

$body = file_get_contents('php://input');
try {
    $event = Webhook::verify($body, $_SERVER['HTTP_X_OCTOREVIEW_SIGNATURE'] ?? '', $secret);
} catch (InvalidSignatureException) {
    http_response_code(401);
    exit;
}
match ($event->type) {
    EventType::ENTITY_RATING_CHANGED => $store->replaceSummary($event->ratingChanged()),
    EventType::AUTHOR_ERASED => $store->forgetAuthor($event->authorErased()->authorId),
    default => null,
};
http_response_code(204);
```

`Webhook::verify()` (also `$client->webhooks()->verify()`) checks `X-Octoreview-Signature` in
constant time with a 300 s tolerance, then decodes the event. Deduplicate on `$event->id`:
delivery is at least once.

## Quickstart

With the server's compose stack running (`make up && make dev-env` in the server repository):

```sh
php examples/quickstart.php path/to/.octoreview-dev.env
```

It creates a review, uploads a photo, and prints the review id, the rating summary and the photo
URL. CI runs it against the pinned server image.

## Errors

Every exception extends `Octoverse\Octoreview\Exception\OctoreviewException`:

| Exception | When |
| --- | --- |
| `ApiException` | the server answered an error: `status`, `errorCode` (an `ErrorCode` constant), `details`, `requestId`, `docUrl`, `retryable`, `retryAfter` |
| `TransportException` | the server could not be reached, or its answer could not be read |
| `NotModifiedException` | a conditional read (`CallOptions::ifNoneMatch($etag)`) found the resource unchanged |
| `MalformedResponseException` | a success response lacks a field this SDK needs |
| `ConfigurationException` | the client was built without a valid base URL, key or tenant |
| `UploadException` | an upload helper failed: `stage`, ids, `requestIds`, `providerCode`, `fix` |
| `InvalidSignatureException` | a webhook signature is missing, wrong or outside the tolerance |

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
([`docs/architecture.md`](docs/architecture.md)). `make integration` runs the quickstart and the
integration suite against the pinned server image, with local and S3 (RustFS) media (needs
Docker).

## License

[Apache License 2.0](LICENSE).
