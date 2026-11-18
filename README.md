# Octoreview PHP SDK

The official PHP SDK for [Octoreview](https://github.com/octoverse-id/octoreview),
the multi-tenant review and rating service with image and video review media.

Server: [octoverse-id/octoreview](https://github.com/octoverse-id/octoreview).

## Status

Bootstrapped: governance files, rulesets and a CI skeleton whose placeholder jobs become real
jobs as the code lands. No release yet. Release line 1 ships the server (local and S3 storage)
with the Go, PHP and JavaScript SDKs; see [`docs/versioning.md`](docs/versioning.md).

## Working on this repository

Read [`AGENTS.md`](AGENTS.md) first (humans too): it holds the rules, gates and development
pipeline. `make help` lists the targets; `make check` is the fast gate.

## License

[Apache License 2.0](LICENSE).
