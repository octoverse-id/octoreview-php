#!/usr/bin/env bash
# Assert that a release tag names the commit where its version FIRST appears (the release PR's
# merge commit) and not a later commit that merely still carries it. Ported from the server's
# scripts/check-release-merge.sh (octoverse-id/octoreview), which explains the hole in full.
#
# publish.yml gates a release on: the commit is on main, it has a green CI run on main, and the
# version stamps equal the tag. Only release PRs bump the version, so every commit merged after
# the release PR passes all three. Tag a docs merge and the release ships the wrong tree while
# every other check reports success, and release tags never move.
#
# The discriminator is the FIRST parent: on the release merge it still carries the previous
# version, and on any later commit it already carries this one. First parent only: on a merge
# commit the second parent is the release branch, which already has the bump.
#
# COMMIT is dereferenced with ^{commit}, so an annotated tag name resolves to its commit.
#
# Usage: check-release-merge.sh VERSION [COMMIT] [REPO_DIR]
#
#   VERSION   the version being published, "0.1.0", "v0.1.0" or "0.2.0-rc.1"
#   COMMIT    the commit the tag points at (default HEAD)
#   REPO_DIR  run against this checkout instead of the current directory (the self-test drives
#             real merge commits through it)
#
# Fails CLOSED on anything it cannot determine (exit 3).
# Exit codes: 0 ok | 1 not the release merge | 2 usage | 3 cannot determine

set -euo pipefail

die() {
  echo "check-release-merge: $1" >&2
  exit "${2:-1}"
}

if [ "$#" -lt 1 ] || [ "$#" -gt 3 ]; then
  echo "usage: check-release-merge.sh VERSION [COMMIT] [REPO_DIR]" >&2
  exit 2
fi

[[ $1 =~ ^v?[0-9]+\.[0-9]+\.[0-9]+(-rc\.[0-9]+)?$ ]] || die "'$1' is not an X.Y.Z or X.Y.Z-rc.N version" 2
version=${1#v}
commit=${2:-HEAD}

if [ "$#" -eq 3 ]; then
  [ -d "$3" ] || die "repo dir '$3' does not exist" 2
  cd "$3"
fi

# The version stamp at a commit: `public const VERSION = 'X';` in src/Version.php
# (scripts/version-check.sh reads the same line).
STAMP=src/Version.php
version_at() {
  local blob
  blob=$(git show "$1:$STAMP" 2>/dev/null) || return 1
  printf '%s\n' "$blob" | sed -nE "s/^ *public const VERSION = '([^']+)';\$/\1/p" | head -n1
}

sha=$(git rev-parse --verify "$commit^{commit}" 2>/dev/null) ||
  die "'$commit' is not a commit in this repository" 3

current=$(version_at "$sha") || die "$STAMP does not exist at $sha" 3
[ -n "$current" ] || die "could not read a version from $STAMP at $sha" 3

if [ "$current" != "$version" ]; then
  echo "FAIL  $sha carries version $current, not $version"
  die "the tag and the tree disagree; tag the commit that bumped to $version" 1
fi

# A root commit has nothing before it, so the version first appears there.
if ! parent=$(git rev-parse --verify "$sha^1" 2>/dev/null); then
  echo "ok    $sha is a root commit; $version first appears here"
  exit 0
fi

# The stamp absent in the parent means this commit introduced it.
if ! previous=$(version_at "$parent"); then
  echo "ok    $sha introduced $STAMP; $version first appears here"
  exit 0
fi
[ -n "$previous" ] || die "could not read a version from $STAMP at parent $parent" 3

if [ "$previous" = "$version" ]; then
  echo "FAIL  $sha is not the release merge for $version"
  echo "      its first parent $parent already carries $version"
  die "tag the release PR's merge commit, not a commit merged after it" 1
fi

echo "ok    $sha is the release merge for $version (first parent had $previous)"
