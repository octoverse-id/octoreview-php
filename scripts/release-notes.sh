#!/usr/bin/env bash
# Print one CHANGELOG section, without its heading: the GitHub release notes (publish.yml).
#
# Usage: scripts/release-notes.sh VERSION|Unreleased [CHANGELOG]
# Exit codes: 0 printed | 1 no such section, or it is empty | 2 usage
set -euo pipefail
[ "$#" -ge 1 ] && [ "$#" -le 2 ] || { echo "usage: release-notes.sh VERSION|Unreleased [CHANGELOG]" >&2; exit 2; }
section=${1#v}
file=${2:-$(dirname "$0")/../CHANGELOG.md}
[ -r "$file" ] || { echo "release-notes: cannot read $file" >&2; exit 2; }

notes=$(awk -v want="## [$section]" '
  index($0, "## [") == 1 { if (found) exit; if (index($0, want) == 1) { found = 1; next } }
  found { print }
' "$file" | sed -e '/./,$!d')
[ -n "$(printf '%s' "$notes" | tr -d '[:space:]')" ] ||
  { echo "release-notes: CHANGELOG has no \"## [$section]\" section with entries" >&2; exit 1; }
printf '%s\n' "$notes"
