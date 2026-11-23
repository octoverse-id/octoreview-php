# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `ApiException::$details` is redacted like the message: every string, recursively (a free-form
  `details.reason` could carry a signed URL into logs).
- `NotModifiedException` carries the 304 response's `requestId`.
- `media()->createUpload()` generates `client_upload_id` when the caller passes `null`, so a
  retried intent always has a stable key.

### Added

- `make integration` (and CI's `integration` job) also runs the quickstart and the suite with
  media on the RustFS S3 emulator, as the server's compose `s3` profile does.
- The client core (PHP 8.2+, PSR-18 with Guzzle by default): `new Client()` and
  `Client::fromEnv()`, immutable `ClientOptions` (application, namespace, actor, HTTP client,
  retry bound) and `CallOptions` (per-call application, namespace or global scope,
  `includeGlobal`, actor, `ifNoneMatch`, request id).
- Every operation of the server's SDK surface as `$client-><resource>()-><method>()`, returning
  readonly models with their ETag and request id, plus `ratings()->get()` by entity key. Unknown,
  misplaced or missing parameters throw before anything is sent.
- Retries by retry class with a bounded `Retry-After`; `createUpload` fills in
  `client_upload_id`; `complete` retries its documented conflicts.
- Typed exceptions (`ApiException` with the 39 `ErrorCode` constants, `TransportException`,
  `NotModifiedException`, `MalformedResponseException`, `ConfigurationException`) whose messages
  never carry the key, a signature or a media token.
- Uploads: `media()->uploadFile()`, `uploadBytes()` and `uploadStream()` (intent, PUT,
  complete). The PUT uses a separate PSR-18 client with only the signed headers and an explicit
  `Content-Length`, follows no redirects, retries on the same URL until it expires, and frees the
  slot after a terminal failure unless another call completed the upload. `UploadException`
  reports the stage, ids, request ids and the provider's code with a fix, never the signed URL.
  Progress reporting through `UploadOptions`.
- Webhooks: `Webhook::verify()` / `$client->webhooks()->verify()` (constant time, 300 s
  tolerance) and typed payloads (`review()`, `media()`, `ratingChanged()`, `authorErased()`),
  checked against the vendored `events.md`.
- `examples/quickstart.php`, the integration suite (`make integration`) against the pinned server
  image, fixtures captured from it (`make fixtures`), the real `integration` CI job, and the
  weekly `contract-drift` workflow.
- The contract gate also checks each operation's response model against the schema the
  contract names for it, both directions, and that decoded fixtures hold non-default values.
  `health()->live()` and `health()->ready()` return `HealthLive` and `HealthReady`, which match
  their schemas (one `HealthStatus` model served both).
- `guzzlehttp/psr7` `^2.5.1` is a declared dependency (the SDK uses its classes directly; Guzzle 7.8
  alone still allows psr7 1.x, which lacks them). CI's `test (8.2)` job also runs the suite with
  the lowest allowed dependencies.
- The offline contract gate against the vendored server contract (`make contract`), real
  `static`, `test (8.2)`, `test (8.3)`, `test (8.4)` and `contract` CI jobs.
- Repository bootstrap: Apache-2.0 license, governance files (AGENTS.md, CONTRIBUTING,
  SECURITY, Code of Conduct), issue forms, PR template, Dependabot with auto-merge for minor and
  patch updates, `llms.txt` with a link check, rulesets mirror and export script, and a CI
  skeleton whose required job names are final (placeholder jobs are replaced as code lands).
