#!/usr/bin/env bash
# Boot the pinned server image and run the quickstart and the integration suite against it
# (make integration; CI `integration`). WRITE_FIXTURES=1 also refreshes testdata/fixtures.
# Usage: scripts/integration.sh <server-image> [port]
set -euo pipefail
cd "$(dirname "$0")/.."
image=$1
port=${2:-18091}
name=octoreview-php-it-$$
work=$(mktemp -d)
trap 'docker rm -f "$name" >/dev/null 2>&1 || true; rm -rf "$work"' EXIT

# One container, SQLite, the local media backend and the demo seed (the server's compose start
# script migrates, seeds .octoreview-dev.env and serves).
docker run -d --name "$name" -p "$port:8000" \
  -e DJANGO_DEBUG=true -e DATABASE_URL=sqlite:////tmp/octoreview-it.sqlite3 \
  -e OCTOREVIEW_STORAGE_LOCATION_ID=php-it -e OCTOREVIEW_PUBLIC_BASE_URL="http://localhost:$port" \
  -e OCTOREVIEW_MEDIA_ROOT=/var/lib/octoreview/media -e OCTOREVIEW_DEMO_SEED=1 \
  -e WEB_CONCURRENCY=1 "$image" sh /app/scripts/compose-start.sh >/dev/null
for attempt in $(seq 1 60); do
  if curl -fsS "http://localhost:$port/api/v1/health/ready" >/dev/null 2>&1; then break; fi
  if [ "$attempt" = 60 ]; then docker logs "$name" >&2; echo "integration: the server did not start" >&2; exit 1; fi
  sleep 1
done
for attempt in $(seq 1 30); do
  docker cp "$name:/var/lib/octoreview/dev/.octoreview-dev.env" "$work/dev.env" >/dev/null 2>&1 && break
  sleep 1
done
echo "integration: $image on port $port"

php examples/quickstart.php "$work/dev.env" | tee "$work/quickstart.out"
for line in review rating photo; do
  grep -q "^$line: " "$work/quickstart.out" || { echo "integration: the quickstart printed no $line line" >&2; exit 1; }
done

OCTOREVIEW_DEV_ENV="$work/dev.env" OCTOREVIEW_WRITE_FIXTURES="${WRITE_FIXTURES:-0}" \
  vendor/bin/phpunit --testsuite integration
