#!/usr/bin/env bash
# Fails when an llms.txt file links a local path that does not exist.
# Usage: scripts/check-llms-txt.sh [file]   (default: llms.txt; paths resolve from the repo root)
# Output on failure: one `<file>:<line> -> <missing path>` per broken link.
set -euo pipefail
cd "$(dirname "$0")/.."
file=${1:-llms.txt}
status=0
n=0
while IFS= read -r line || [ -n "$line" ]; do
  n=$((n + 1))
  while IFS= read -r target; do
    [ -n "$target" ] || continue
    case "$target" in http://* | https://* | mailto:* | '#'*) continue ;; esac
    path=${target%%#*}
    if [ ! -e "$path" ]; then
      echo "$file:$n -> $path"
      status=1
    fi
  done < <(printf '%s\n' "$line" | grep -oE '\]\([^)]+\)' | sed -E 's/^\]\((.*)\)$/\1/' || true)
done <"$file"
if [ "$status" -eq 0 ]; then echo "check-llms-txt OK: $file"; fi
exit "$status"
