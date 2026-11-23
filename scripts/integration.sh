#!/usr/bin/env bash
# Boot the pinned server image and run the quickstart and the integration suite against it
# (make integration; CI `integration`). WRITE_FIXTURES=1 also refreshes testdata/fixtures.
#
# Usage: scripts/integration.sh <server-image> [local|s3]
#   local  one container: SQLite, the local media backend and the demo seed (default)
#   s3     the same server with media on the RustFS S3 emulator, as the server's compose `s3`
#          profile runs it (server docs/spec.md section 19): uploads go to presigned URLs
set -euo pipefail
cd "$(dirname "$0")/.."
image=$1
mode=${2:-local}
case "$mode" in
  local) port=18091 ;;
  s3) port=18191 s3port=19191 ;;
  *) echo "integration: unknown mode $mode (local or s3)" >&2; exit 2 ;;
esac
# The server compose file's emulator, pinned by the same digest.
rustfs=rustfs/rustfs:1.0.6@sha256:ee717072290bec9b11abfc0365f8310b00df1cd9b3639943edae47bdef05044a
tag=octoreview-php-it-$$
work=$(mktemp -d)
cleanup() {
  docker rm -f "$tag" "$tag-s3" >/dev/null 2>&1 || true
  docker network rm "$tag" >/dev/null 2>&1 || true
  rm -rf "$work"
}
trap cleanup EXIT

storage=(-e OCTOREVIEW_STORAGE_LOCATION_ID="php-it-$mode")
if [ "$mode" = s3 ]; then
  s3=(-e OCTOREVIEW_STORAGE_S3_BUCKET=octoreview-media -e OCTOREVIEW_STORAGE_S3_REGION=us-east-1
    -e OCTOREVIEW_STORAGE_S3_ENDPOINT_URL="http://$tag-s3:9000"
    -e AWS_ACCESS_KEY_ID=octoreview-dev -e AWS_SECRET_ACCESS_KEY=octoreview-dev-secret)
  docker network create "$tag" >/dev/null
  docker run -d --name "$tag-s3" --network "$tag" -p "$s3port:9000" \
    -e RUSTFS_ACCESS_KEY=octoreview-dev -e RUSTFS_SECRET_KEY=octoreview-dev-secret "$rustfs" >/dev/null
  # The bucket, its staging lifecycle rule and CORS, as the compose profile's s3-init creates them.
  docker run --rm --network "$tag" "${s3[@]}" --entrypoint python "$image" /app/scripts/compose-s3-init.py
  # The server reaches the emulator by name; presigned URLs are signed for the host this SDK uses.
  storage+=(--network "$tag" -e OCTOREVIEW_STORAGE_BACKEND=s3 "${s3[@]}"
    -e OCTOREVIEW_STORAGE_S3_PUBLIC_ENDPOINT_URL="http://localhost:$s3port"
    -e OCTOREVIEW_STORAGE_S3_ADDRESSING_STYLE=path)
fi
docker run -d --name "$tag" -p "$port:8000" "${storage[@]}" \
  -e DJANGO_DEBUG=true -e DATABASE_URL=sqlite:////tmp/octoreview-it.sqlite3 \
  -e OCTOREVIEW_PUBLIC_BASE_URL="http://localhost:$port" \
  -e OCTOREVIEW_MEDIA_ROOT=/var/lib/octoreview/media -e OCTOREVIEW_DEMO_SEED=1 \
  -e WEB_CONCURRENCY=1 "$image" sh /app/scripts/compose-start.sh >/dev/null
for attempt in $(seq 1 60); do
  if curl -fsS "http://localhost:$port/api/v1/health/ready" >/dev/null 2>&1; then break; fi
  if [ "$attempt" = 60 ]; then docker logs "$tag" >&2; echo "integration: the server did not start" >&2; exit 1; fi
  sleep 1
done
for attempt in $(seq 1 30); do
  docker cp "$tag:/var/lib/octoreview/dev/.octoreview-dev.env" "$work/dev.env" >/dev/null 2>&1 && break
  sleep 1
done
echo "integration: $image on port $port, media on $mode"

# --- this SDK's quickstart and integration suite ------------------------------------------------
php examples/quickstart.php "$work/dev.env" | tee "$work/quickstart.out"
for line in review rating photo; do
  grep -q "^$line: " "$work/quickstart.out" || { echo "integration: the quickstart printed no $line line" >&2; exit 1; }
done

OCTOREVIEW_DEV_ENV="$work/dev.env" OCTOREVIEW_WRITE_FIXTURES="${WRITE_FIXTURES:-0}" \
  vendor/bin/phpunit --testsuite integration
