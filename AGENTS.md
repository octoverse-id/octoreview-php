# Octoreview PHP SDK Agent Instructions

The official PHP client for Octoreview, the multi-tenant review and rating service
(`octoverse-id/octoreview`). It mirrors the server's REST contract and adds an upload helper for
review images and videos.

## Status

This repository was bootstrapped with governance files and a CI skeleton. The approved plan
(octoreview workspace `octoreview-plan.md`, reviewed with /autoplan on 2026-11-18) drives the
work. Release line 1 ships the server with the `local` and `s3` (AWS S3 and S3-compatible)
storage backends, plus the Go, PHP and JavaScript SDKs.

The server's `docs/spec.md` and `docs/openapi.yaml` are the contract. This SDK vendors
`docs/openapi.yaml`, records the tracked server version in `docs/versioning.md`, and its contract
gate fails when a called operation, header, parameter or field drifts from it.

## SDK Rules

- Same resource surface in every Octoreview SDK; the server's SDK surface file names each method.
- Never send service credentials (`Authorization`, `X-Tenant-ID`, namespace headers) on the
  media upload PUT; it goes to `upload.url` with only `upload.headers`.
- Errors are typed, carry the request id, and never include the API key or a signed URL.
- Unit tests never touch the network; integration tests run against the real server container.

## Gates

- `make help` lists every target. `make check` is the fast gate and needs only the language
  toolchain (no Docker, no network). `make release-check` is the full gate (needs Docker once
  integration targets land) and fails while any CI job is still a bootstrap placeholder.
- CI runs the same targets. Required check names: `static`, `test (8.2)`, `test (8.3)`, `test (8.4)`, `contract`, `integration`.

## Development Pipeline

- Branch names follow Conventional Branch naming (https://conventional-branch.github.io/):
  `<type>/<description>`, lowercase alphanumerics, hyphens and dots. Allowed types: `feature`,
  `feat`, `bugfix`, `fix`, `hotfix`, `release`, `chore`.
- Planned work starts with a GitHub issue. When the user asks to implement an approved plan,
  create the issue before the branch (or use the issue number the user gives); it carries the plan
  summary, key tasks and acceptance checks. If issue creation fails, stop and report the blocker.
- Planned-work branches include the issue number: `<type>/<issue-number>-<short-description>`,
  for example `feature/12-review-media`.
- PR bodies include `Closes #<issue-number>` and map the change back to the plan.
- `main` is protected by the `main: require PR + green CI` ruleset: every change lands through a
  PR whose required checks are green; nobody bypasses it.
- A PR that adds or renames a required CI job follows this order: wait for the PR's CI to report
  the new check name; update the live ruleset (a human with repo admin; agents stop and ask);
  run `make export-rulesets` and commit the mirror diff to the same PR; merge; tell other open PRs
  to rebase.
- Releases follow Semantic Versioning with the policy in `docs/versioning.md` and the runbook in
  `docs/release.md`. Version bumps and tags happen only in a dedicated `release/<version>` PR,
  never in feature or fix PRs. Release tags never move.
- The `code-review/` directory is reserved for local review pipeline artifacts. Review agents
  write findings to `code-review/findings.md`; patch agents read it, apply valid fixes and write
  `code-review/patches.md`. Never stage or commit anything under `code-review/` except the tracked
  `code-review/.gitkeep`; after creating a PR, delete the local artifacts.

## Web Browsing

- Use the `/browse` skill from gstack for all web browsing.
- Do not use `mcp__claude-in-chrome__*` tools.
