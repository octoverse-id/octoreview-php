# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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
- The offline contract gate against the vendored server contract (`make contract`), real
  `static`, `test (8.2)`, `test (8.3)`, `test (8.4)` and `contract` CI jobs.
- Repository bootstrap: Apache-2.0 license, governance files (AGENTS.md, CONTRIBUTING,
  SECURITY, Code of Conduct), issue forms, PR template, Dependabot with auto-merge for minor and
  patch updates, `llms.txt` with a link check, rulesets mirror and export script, and a CI
  skeleton whose required job names are final (placeholder jobs are replaced as code lands).
