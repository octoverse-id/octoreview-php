# Release runbook

Releases of octoreview-php are cut from `main` through a dedicated PR. This runbook grows with the
release workflow; until the first release it records the invariants every release keeps.

## Invariants

- A release PR is named `release/<version>` and only bumps the version and moves the
  `[Unreleased]` CHANGELOG entries under the new version heading.
- The tag `v<version>` is created on the merge commit of that PR after its CI is green.
- Release tags never move: the `release tags: never move` ruleset rejects re-pointing or
  force-pushing a `v*` tag, admins included. Only repo admins can delete a wrong tag
  (`release tags: deletion is admin-only`), and the version is then re-cut, not re-used.
- `make release-check` must pass on the release commit.

## Steps

1. Open an issue for the release and a `release/<version>` branch from `main`.
2. Bump the version and update `CHANGELOG.md`; open the PR with `Closes #<issue-number>`.
3. Merge when CI is green, then tag the merge commit `v<version>` and push the tag.
4. Publishing steps for this repository are added here by the PR that adds the publish workflow.
