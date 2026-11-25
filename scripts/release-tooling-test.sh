#!/usr/bin/env bash
# Proves the release scripts pass and fail as documented (make check): check-release-merge.sh
# against real merge commits (its first-parent rule cannot be tested any other way),
# version-check.sh and release-notes.sh. A publish runs once per tag and tags never move, so
# these guards are tested here rather than discovered broken on a release.
set -euo pipefail
cd "$(dirname "$0")/.."
scripts=$PWD/scripts
repo=$(mktemp -d "${TMPDIR:-/tmp}/release-tooling.XXXXXX")
trap 'rm -rf "$repo" "$repo.root"' EXIT

# Hermetic git: no global or system config (a CI runner has no identity, a laptop may sign
# commits or run hooks), and one identity for every commit and tag below.
export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1
export GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@example.invalid
export GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@example.invalid

# --- this repository's version stamp -----------------------------------------------------------
write_stamp() { # DIR VERSION
  mkdir -p "$1/src"
  printf "<?php\n\nfinal class Version\n{\n    public const VERSION = '%s';\n}\n" "$2" >"$1/src/Version.php"
}
remove_stamp() { rm -f "$1/src/Version.php"; }
# ------------------------------------------------------------------------------------------------

failures=0
expect() { # CODE LABEL -- COMMAND...
  local want=$1 label=$2 got=0
  shift 3
  "$@" >/dev/null 2>&1 || got=$?
  if [ "$got" != "$want" ]; then
    echo "release-tooling-test FAILED: $label: exit $got, want $want" >&2
    failures=$((failures + 1))
  fi
}
g() { git -C "$repo" "$@"; }
changelog() { # DIR HEADING...
  local dir=$1
  shift
  { printf '# Changelog\n\n## [Unreleased]\n\n'; for h in "$@"; do printf '%s\n\n- An entry.\n\n' "$h"; done; } >"$dir/CHANGELOG.md"
}
commit() { g add -A && g commit -q -m "$1" && g rev-parse HEAD; }
release() { # VERSION DATE: a release PR merged with a merge commit; prints the merge sha
  g switch -q -c "release/$1"
  write_stamp "$repo" "$1"
  changelog "$repo" "## [$1] - $2" "${@:3}"
  commit "release: $1" >/dev/null
  g switch -q main
  g merge -q --no-ff -m "Merge release/$1" "release/$1"
  g rev-parse HEAD
}

g init -q -b main
write_stamp "$repo" 0.1.0-dev
changelog "$repo"
root=$(commit "root")
echo feature >"$repo/feature.txt"
commit "feature" >/dev/null

m1=$(release 0.1.0 2026-11-25)
g tag -a v0.1.0 -m v0.1.0 "$m1"
echo later >>"$repo/feature.txt"
later=$(commit "a fix after the release")
g switch -q -c docs/after
echo docs >"$repo/docs.txt"
commit "docs" >/dev/null
g switch -q main
g merge -q --no-ff -m "Merge docs/after" docs/after
m2=$(g rev-parse HEAD)

expect 0 "the release merge" -- "$scripts/check-release-merge.sh" 0.1.0 "$m1" "$repo"
expect 0 "an annotated tag resolves to its commit" -- "$scripts/check-release-merge.sh" v0.1.0 v0.1.0 "$repo"
expect 1 "a later commit carrying the version" -- "$scripts/check-release-merge.sh" 0.1.0 "$later" "$repo"
expect 1 "a later merge, judged by its first parent" -- "$scripts/check-release-merge.sh" 0.1.0 "$m2" "$repo"
expect 1 "a tag that disagrees with the tree" -- "$scripts/check-release-merge.sh" 0.1.1 "$m1" "$repo"
expect 3 "an unknown commit fails closed" -- "$scripts/check-release-merge.sh" 0.1.0 0000000 "$repo"
expect 2 "no arguments" -- "$scripts/check-release-merge.sh"
expect 2 "not a version" -- "$scripts/check-release-merge.sh" 1.0 "$m1" "$repo"
expect 2 "a missing repo dir" -- "$scripts/check-release-merge.sh" 0.1.0 "$m1" "$repo/missing"

rc=$(release 0.2.0-rc.1 2026-11-26 "## [0.1.0] - 2026-11-25")
echo more >>"$repo/feature.txt"
after_rc=$(commit "a fix after the candidate")
final=$(release 0.2.0 2026-11-27 "## [0.2.0-rc.1] - 2026-11-26" "## [0.1.0] - 2026-11-25")
expect 0 "a release candidate merge" -- "$scripts/check-release-merge.sh" 0.2.0-rc.1 "$rc" "$repo"
expect 1 "a candidate tag on a later commit" -- "$scripts/check-release-merge.sh" 0.2.0-rc.1 "$after_rc" "$repo"
expect 0 "the final release after its candidates" -- "$scripts/check-release-merge.sh" 0.2.0 "$final" "$repo"

mkdir "$repo.root"
write_stamp "$repo.root" 0.1.0
git -C "$repo.root" init -q -b main
git -C "$repo.root" add -A
git -C "$repo.root" commit -q -m root
expect 0 "a root commit" -- "$scripts/check-release-merge.sh" 0.1.0 HEAD "$repo.root"

# version-check reads the working tree.
g checkout -q "$m1"
expect 0 "agreeing stamps" -- "$scripts/version-check.sh" "" "$repo"
expect 0 "agreeing stamps, as the tag names them" -- "$scripts/version-check.sh" v0.1.0 "$repo"
expect 1 "stamps that disagree with the tag" -- "$scripts/version-check.sh" 0.1.1 "$repo"
printf '{\n  "name": "example/sdk",\n  "version": "0.1.0"\n}\n' >"$repo/composer.json"
expect 1 "composer.json setting a version" -- "$scripts/version-check.sh" "" "$repo"
rm "$repo/composer.json"
g checkout -q "$root"
expect 1 "a development version" -- "$scripts/version-check.sh" "" "$repo"
g checkout -q "$final"
write_stamp "$repo" 0.3.0
expect 1 "no CHANGELOG heading for the version" -- "$scripts/version-check.sh" "" "$repo"
remove_stamp "$repo"
expect 2 "an unreadable stamp" -- "$scripts/version-check.sh" "" "$repo"
g checkout -q -f "$final"

notes=$("$scripts/release-notes.sh" 0.2.0 "$repo/CHANGELOG.md")
[ "$notes" = "- An entry." ] || { echo "release-tooling-test FAILED: release notes: got '$notes'" >&2; failures=$((failures + 1)); }
expect 0 "notes for a candidate" -- "$scripts/release-notes.sh" v0.2.0-rc.1 "$repo/CHANGELOG.md"
expect 1 "notes for a version without a section" -- "$scripts/release-notes.sh" 9.9.9 "$repo/CHANGELOG.md"
expect 1 "notes for an empty section" -- "$scripts/release-notes.sh" Unreleased "$repo/CHANGELOG.md"
expect 2 "notes without arguments" -- "$scripts/release-notes.sh"

[ "$failures" = 0 ] || exit 1
echo "release-tooling-test OK"
