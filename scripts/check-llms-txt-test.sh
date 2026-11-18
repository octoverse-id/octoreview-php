#!/usr/bin/env bash
# Proves check-llms-txt.sh can still fail: a fixture with one broken link must exit 1 and name it.
set -euo pipefail
cd "$(dirname "$0")/.."
fixture=$(mktemp "${TMPDIR:-/tmp}/llms-fixture.XXXXXX")
trap 'rm -f "$fixture"' EXIT
printf '# fixture\n\n- [Agents](AGENTS.md)\n- [Missing](docs/does-not-exist.md)\n' >"$fixture"
if out=$(./scripts/check-llms-txt.sh "$fixture"); then
  echo "check-llms-txt-test FAILED: a broken link passed" >&2
  exit 1
fi
case "$out" in
  *"$fixture:4 -> docs/does-not-exist.md"*) echo "check-llms-txt-test OK" ;;
  *) echo "check-llms-txt-test FAILED: unexpected output: $out" >&2; exit 1 ;;
esac
