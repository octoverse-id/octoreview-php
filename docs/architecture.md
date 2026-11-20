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
| `src/Exception/*.php`, `src/ErrorCode.php` | `ApiException` and the 39 code constants, `TransportException`, `NotModifiedException`, `MalformedResponseException`, `ConfigurationException` |

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
  (reflection; the upload and webhook helpers are listed as pending until the next PR, and the
  test fails if one exists while still listed);
- the error code constants equal the contract's `ErrorCode` enum;
- a recording PSR-18 client calls every operation with self-describing values (`v:entity_type`,
  `v/review_id`, distinct numbers): method, escaped path, each value under its own name in the
  query or the body, credentials, namespace and actor headers exactly where the contract declares
  them. Each call decodes a real server response from `testdata/fixtures`;
- the README's retry table matches the operation table.

## Retries

The transport retries by the surface's retry class: `safe`, `idempotent` and `keyed` calls are
retried on network failures, 503 and 429 (and `complete` on `complete_in_progress` and
`upload_changed`), up to `maxAttempts`, honoring `Retry-After` up to `maxRetryAfterSeconds`;
`unsafe` calls never are. `createUpload` fills in `client_upload_id` (a UUID v4) before the
first attempt, so every retry sends the same key.
