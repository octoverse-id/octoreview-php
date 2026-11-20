#!/usr/bin/env bash
# Vendor the server contract as JSON for the offline contract gate (make vendor-contract).
# PHP reads JSON without extensions (YAML needs ext-yaml or a library), so the
# server's docs/openapi.yaml and docs/sdk-surface.yaml are converted here.
#
# Usage: scripts/vendor-contract.sh [SERVER_REF]
#   SERVER_REF  a git ref of octoverse-id/octoreview (default: main), or a local checkout path.
set -euo pipefail
cd "$(dirname "$0")/.."
ref=${1:-main}
out=testdata/contract
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

if [ -d "$ref" ]; then
  cp "$ref/docs/openapi.yaml" "$ref/docs/sdk-surface.yaml" "$ref/docs/events.md" "$work/"
  source="octoverse-id/octoreview@$(git -C "$ref" rev-parse HEAD) (from a local checkout)"
else
  sha=$(gh api "repos/octoverse-id/octoreview/commits/$ref" --jq .sha)
  for file in openapi.yaml sdk-surface.yaml events.md; do
    curl -fsSL "https://raw.githubusercontent.com/octoverse-id/octoreview/$sha/docs/$file" -o "$work/$file"
  done
  source="octoverse-id/octoreview@$sha"
fi

convert() {
  if command -v uv >/dev/null; then
    uv run --quiet --no-project --with 'pyyaml>=6,<7' python -I - "$@"
  else
    python3 -I - "$@"
  fi
}
convert "$work/openapi.yaml" "$out/openapi.json" "$work/sdk-surface.yaml" "$out/sdk-surface.json" <<'PY'
import json, sys
import yaml
pairs = zip(sys.argv[1::2], sys.argv[2::2])
for src, dst in pairs:
    with open(src) as handle:
        document = yaml.safe_load(handle)
    with open(dst, "w") as handle:
        json.dump(document, handle, indent=1, sort_keys=True)
        handle.write("\n")
PY
cp "$work/events.md" "$out/events.md"
printf '%s\n' "$source" >"$out/SOURCE"
echo "vendored the contract from $source into $out/"
