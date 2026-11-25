#!/usr/bin/env bash
# The version stamps of a release must agree (make version-check; docs/release.md). publish.yml
# runs it with the tag's version; a release PR makes it pass.
#
#   src/Version.php  public const VERSION = '0.1.0';   (sent in the User-Agent)
#   CHANGELOG.md     a "## [0.1.0] - YYYY-MM-DD" heading
#
# composer.json carries no version: Packagist reads it from the tag. A development version
# (0.1.0-dev) is never releasable, so this fails on main between releases.
#
# Usage: scripts/version-check.sh [VERSION] [REPO_DIR]
#   VERSION   the version the stamps must name ("0.1.0" or "v0.1.0"); default: whatever they say
# Exit codes: 0 agree | 1 disagree or not releasable | 2 unreadable or usage
set -euo pipefail
expected=${1:-}
expected=${expected#v}
cd "${2:-$(dirname "$0")/..}"

stamp=$(sed -nE "s/^ *public const VERSION = '([^']+)';\$/\1/p" src/Version.php 2>/dev/null | head -n1)
[ -n "$stamp" ] || { echo "version-check: could not read the version from src/Version.php" >&2; exit 2; }

echo "src/Version.php=$stamp"
status=0
if ! [[ $stamp =~ ^[0-9]+\.[0-9]+\.[0-9]+(-rc\.[0-9]+)?$ ]]; then
  echo "FAIL  $stamp is not a releasable X.Y.Z or X.Y.Z-rc.N version (a release PR sets one)"
  status=1
fi
if [ -n "$expected" ] && [ "$stamp" != "$expected" ]; then
  echo "FAIL  src/Version.php says $stamp, not $expected"
  status=1
fi
if [ -f composer.json ] && grep -qE '^  "version":' composer.json; then
  echo "FAIL  composer.json sets \"version\"; Packagist must read it from the tag"
  status=1
fi
grep -qE "^## \[$(printf '%s' "$stamp" | sed 's/\./\\./g')\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$" CHANGELOG.md ||
  { echo "FAIL  CHANGELOG.md has no \"## [$stamp] - YYYY-MM-DD\" heading"; status=1; }
[ "$status" = 0 ] && echo "version-check OK ($stamp)"
exit "$status"
