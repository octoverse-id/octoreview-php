# Contributing to Octoreview PHP SDK

Thanks for your interest in contributing. By participating, you agree to abide by our
[Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

Read [`AGENTS.md`](AGENTS.md): the rules there apply to humans and agents alike.

## Quality gates

```bash
make help           # list targets
make check          # fast gate, toolchain only
make release-check  # full gate
```

CI runs the same targets, and every required check must be green before a PR can merge.

## Branches, commits and PRs

- Conventional Branch names: `<type>/<description>`; with an issue,
  `<type>/<issue-number>-<description>` (for example `feature/12-review-media`).
- Planned work starts from a GitHub issue; PR bodies include `Closes #<issue-number>`.
- Keep PRs focused, include tests, and note new env vars, migrations or API surface.
- Add a `CHANGELOG.md` entry under `[Unreleased]` for user-facing changes.

## Changelog and versioning

- Semantic Versioning; see [`docs/versioning.md`](docs/versioning.md).
- Do not bump versions in feature or fix PRs. Releases happen only in a dedicated
  `release/<version>` PR, following [`docs/release.md`](docs/release.md).

## Reporting security issues

Do **not** open public issues for vulnerabilities. See [`SECURITY.md`](SECURITY.md).

## License

By contributing, you agree that your contributions are licensed under the
[Apache License 2.0](LICENSE).
