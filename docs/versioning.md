# Versioning policy

Octoreview PHP SDK follows [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## Release line policy

This SDK ships `0.x` releases until the server's first consumer integration passes, then
`1.0.0`. octonomy-php's "no 0.x" rule is deliberately not copied.

## PHP version

PHP 8.2 or newer: PHP 8.1 reached end of life on 2025-12-31, so there is no older line. CI tests
8.2, 8.3 and 8.4.

## Server compatibility

| SDK | Server contract |
| --- | --- |
| unreleased (`main`) | `docs/openapi.yaml` at the commit in `testdata/contract/SOURCE` (server `1.0.0-rc.2` line; `1.0.0-rc.1` was never tagged) |

## The server image CI runs against

`SERVER_IMAGE` in the Makefile pins the `integration` job to one published image of the
server's `main` branch: `ghcr.io/octoverse-id/octoreview:sha-c324de22fab81afd0770b283f1447d2eeb6376e9`.
A bump PR moves it (and refreshes `testdata/contract` and `testdata/fixtures` with
`make vendor-contract SERVER=<commit>` and `make fixtures`). The weekly `contract-drift`
workflow runs the same suite against `:edge` and opens an issue when it fails.

## Rules

- Pre-1.0 breaking changes need a CHANGELOG migration note with before/after code.
- `docs/versioning.md` keeps a server/SDK compatibility table once the first release exists.
- Deprecated fields and methods keep working, with a warning, for at least one minor release.
- Version bumps and tags happen only in a `release/<version>` PR (see `docs/release.md`).
