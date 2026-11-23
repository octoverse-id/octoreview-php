# Architecture

How the PHP SDK is built. The server's `docs/spec.md` section 18 is the contract for every SDK;
this page records how this one meets it. octoreview-go is the reference implementation; both
share the operation table's shape, the retry classes and the gate's checks.

## Layout

| File | Owns |
| --- | --- |
| `src/Client.php`, `src/ClientOptions.php`, `src/CallOptions.php` | `new Client()`, `Client::fromEnv()`, immutable client defaults and per-call overrides |
| `src/Operations.php`, `src/Operation.php` | One table of every REST operation: id, method, path, retry class, surface name, required and optional parameters, and which travel in the body |
| `src/Transport.php`, `src/Redactor.php` | Parameter checks and the query/body split, headers, path escaping, application and namespace defaults, retries, the data envelope, error parsing and redaction |
| `src/Resource/*Service.php` | `$client-><resource>()-><method>(...)` for every operation, plus the `ratings()->get` helper |
| `src/Model/*.php` | Readonly response models, `Page`, and `Data` (typed reads that fail loudly on a missing field) |
| `src/Exception/*.php`, `src/ErrorCode.php` | `ApiException` and the 39 code constants, `TransportException`, `NotModifiedException`, `MalformedResponseException`, `ConfigurationException`, `UploadException`, `InvalidSignatureException` |
| `src/Upload/*.php`, `src/UploadOptions.php` | The media PUT (`Uploader`), progress reporting, and the upload helpers' options |
| `src/Webhook/*.php` | `Webhook::verify()`, the `Event` envelope, the four payload types and the event type constants |
| `examples/quickstart.php` | The quickstart CI runs against the real server |

Dependencies: PSR-18/PSR-17 interfaces, with Guzzle 7 as the default client
(`ClientOptions::withHttpClient()` takes any PSR-18 client).

**Parameters** are arrays keyed by the server's wire names. `Transport::split()` refuses an
unknown key, a path value, `application_id` and `include_global` (they come from `CallOptions` and
the client defaults), checks the contract's required parameters, then sends each parameter where
the contract puts it: the body when the operation lists it as a body field, the query otherwise.
`metadata` and `author_metadata` encode as JSON objects even when empty, and an operation
without body fields (for example `complete`) sends no body.

## The contract gate

`make check` runs the gate offline (`make contract` runs it alone) against
`testdata/contract/`: the server's `docs/openapi.yaml` and `docs/sdk-surface.yaml`, vendored as
JSON by `make vendor-contract` (PHP reads JSON without extensions), plus `events.md`. `SOURCE`
names the server commit. `tests/ContractTest.php` checks:

- the operation table equals the contract: every operation (except `sdk: none`) has one row, with
  the same method, path, body fields and query parameters (both directions);
- each row's PHP name, retry class, required and optional parameters equal the surface's;
- every surface method and helper exists on the client, and the client declares nothing else
  (reflection);
- the error code constants equal the contract's `ErrorCode` enum;
- a recording PSR-18 client calls every operation with self-describing values (`v:entity_type`,
  `v/review_id`, distinct numbers): method, escaped path, each value under its own name in the
  query or the body, credentials, namespace and actor headers exactly where the contract declares
  them. Each call decodes a real server response from `testdata/fixtures`, captured by this
  SDK's integration suite (`make fixtures`);
- the model each method decodes into declares exactly the properties of the schema the contract
  names for that operation's response (`data`, or each item of a list), and the decoded fixture
  holds mostly non-default values;
- the README's retry table matches the operation table;
- every event type and payload field in the vendored `events.md` exists in `src/Webhook`, and
  nothing else (`tests/WebhookTest.php`).

`.github/workflows/contract-drift.yml` runs weekly. It vendors the newest server contract (the
newest `-rc.N` tag until a final release exists), runs the gate against it and the integration
suite against `:edge`, and opens an issue when either fails.

## Retries

The transport retries by the surface's retry class: `safe`, `idempotent` and `keyed` calls are
retried on network failures, 503 and 429 (and `complete` on `complete_in_progress` and
`upload_changed`), up to `maxAttempts`, honoring `Retry-After` up to `maxRetryAfterSeconds`;
`unsafe` calls never are. `createUpload` fills in `client_upload_id` (a UUID v4) before the
first attempt, so every retry sends the same key.

Exceptions never chain the HTTP client's own exception: its message can carry the key or a
signed URL, and PHP prints the whole chain when an exception is cast to a string.

## Uploads (`src/Upload`, `MediaService::upload*`)

`uploadFile`, `uploadBytes` and `uploadStream` compose `createUpload`, a PUT and `complete`.

- **The PUT** uses its own PSR-18 client (`ClientOptions::withUploadHttpClient()`). The default
  Guzzle client follows no redirects, has no total timeout but fails a transfer under 1 byte/s
  for 60 s, and never sends `Expect: 100-continue`. Only the signed headers plus an explicit
  `Content-Length` are sent, so credentials never reach a storage provider.
- **Retries:** network failures, 429 and 5xx retry up to 3 times on the same URL while it stays
  valid, rewinding the stream; a `Retry-After` past the URL's expiry ends the upload.
- **Failures:** S3's XML `<Code>` (or the local backend's JSON code) becomes `providerCode` with a
  `fix`. A terminal PUT failure asks `complete` first: a ready answer means another call with the
  same client upload id finished the upload, so that media is returned; only `upload_not_found`
  (no bytes landed) deletes the pending media.

## The integration suite

`make integration` boots the pinned server image as one container: SQLite, the local backend,
and the demo seed through the server's compose start script. It then runs again with media on
the RustFS S3 emulator, as the server's compose `s3` profile runs it (spec section 19): the
emulator pinned by the same digest, the bucket created by the image's own `compose-s3-init.py`,
and the server signing URLs for the host this SDK reaches, so uploads go through presigned S3
PUTs. It copies out
`.octoreview-dev.env`, runs `examples/quickstart.php`, then runs the `integration` test suite
(`tests/Integration`) over the main flows:
- entity media limits, two uploads and the third refused;
- ratings by key and in batch;
- pre-moderation, the version guard and revisions;
- media moderation, erasure, audit logs and health.

It restores the application's settings afterwards. `make fixtures` also saves one response per
operation into `testdata/fixtures`.
