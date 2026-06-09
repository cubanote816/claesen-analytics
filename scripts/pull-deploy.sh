#!/usr/bin/env bash
set -euo pipefail

die() {
  echo "ERROR: $*" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

REPO="${GITHUB_REPOSITORY:-cubanote816/claesen-analytics}"
TAG="${GITHUB_RELEASE_TAG:-production-latest}"
ARCHIVE_ASSET="${GITHUB_RELEASE_ASSET:-release.tar.gz}"
CHECKSUM_ASSET="${GITHUB_RELEASE_CHECKSUM_ASSET:-release.tar.gz.sha256}"
METADATA_ASSET="${GITHUB_RELEASE_METADATA_ASSET:-release.env}"
PHP_BIN="${PHP_BIN:-php}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
MAINTENANCE_SECRET="${DEPLOY_MAINTENANCE_SECRET:-admin-update}"

: "${DEPLOY_PATH:?Set DEPLOY_PATH to the app base path, for example /var/www/claesen-analytics}"

DEPLOY_PATH="${DEPLOY_PATH%/}"
RELEASES_PATH="$DEPLOY_PATH/releases"
SHARED_PATH="$DEPLOY_PATH/shared"
CURRENT_PATH="$DEPLOY_PATH/current"
TMP_PATH="$DEPLOY_PATH/tmp"

require_cmd curl
require_cmd tar
require_cmd sha256sum
require_cmd "$PHP_BIN"
require_cmd mktemp

mkdir -p "$RELEASES_PATH" "$SHARED_PATH" "$TMP_PATH"

if [ ! -f "$SHARED_PATH/.env" ]; then
  die "Missing $SHARED_PATH/.env. Create it once before the first deploy."
fi

WORK_DIR="$(mktemp -d "$TMP_PATH/pull-deploy.XXXXXX")"
MAINTENANCE_ENABLED=0

cleanup() {
  local status=$?
  if [ "$status" -ne 0 ] && [ "$MAINTENANCE_ENABLED" = "1" ] && [ -f "$CURRENT_PATH/artisan" ]; then
    "$PHP_BIN" "$CURRENT_PATH/artisan" up >/dev/null 2>&1 || true
  fi
  rm -rf "$WORK_DIR"
  exit "$status"
}
trap cleanup EXIT

json_headers=(
  -H "Accept: application/vnd.github+json"
  -H "X-GitHub-Api-Version: 2022-11-28"
)
asset_headers=(
  -H "Accept: application/octet-stream"
  -H "X-GitHub-Api-Version: 2022-11-28"
)

if [ -n "${GITHUB_TOKEN:-}" ]; then
  json_headers+=(-H "Authorization: Bearer $GITHUB_TOKEN")
  asset_headers+=(-H "Authorization: Bearer $GITHUB_TOKEN")
fi

RELEASE_JSON="$WORK_DIR/release.json"
RELEASE_API="https://api.github.com/repos/$REPO/releases/tags/$TAG"

echo "Reading GitHub release $REPO@$TAG..."
if ! curl -fsSL "${json_headers[@]}" "$RELEASE_API" -o "$RELEASE_JSON"; then
  die "Could not read $RELEASE_API. If the repo is private, set GITHUB_TOKEN with read-only repository contents access."
fi

asset_id_for() {
  ASSET_NAME="$1" RELEASE_JSON="$RELEASE_JSON" "$PHP_BIN" -r '
    $json = json_decode(file_get_contents(getenv("RELEASE_JSON")), true);
    $name = getenv("ASSET_NAME");

    if (!is_array($json)) {
        fwrite(STDERR, "Invalid GitHub release JSON\n");
        exit(2);
    }

    foreach (($json["assets"] ?? []) as $asset) {
        if (($asset["name"] ?? "") === $name) {
            echo $asset["id"];
            exit(0);
        }
    }

    fwrite(STDERR, "Asset not found: {$name}\n");
    exit(3);
  '
}

download_asset() {
  local name="$1"
  local output="$2"
  local asset_id

  asset_id="$(asset_id_for "$name")"
  echo "Downloading $name..."
  curl -fL "${asset_headers[@]}" \
    "https://api.github.com/repos/$REPO/releases/assets/$asset_id" \
    -o "$output"
}

download_asset "$ARCHIVE_ASSET" "$WORK_DIR/$ARCHIVE_ASSET"
download_asset "$CHECKSUM_ASSET" "$WORK_DIR/$CHECKSUM_ASSET"
download_asset "$METADATA_ASSET" "$WORK_DIR/$METADATA_ASSET"

echo "Verifying checksum..."
(cd "$WORK_DIR" && sha256sum -c "$CHECKSUM_ASSET")

release_sha="$(sed -n 's/^GITHUB_SHA=//p' "$WORK_DIR/$METADATA_ASSET" | head -n 1)"
release_short_sha="${release_sha:0:12}"
release_stamp="$(date -u +%Y%m%d%H%M%S)"
release_name="$release_stamp-${release_short_sha:-unknown}"
release_path="$RELEASES_PATH/$release_name"

echo "Preparing release $release_name..."
mkdir -p "$release_path" \
         "$SHARED_PATH/storage/app/public" \
         "$SHARED_PATH/storage/framework/cache" \
         "$SHARED_PATH/storage/framework/sessions" \
         "$SHARED_PATH/storage/framework/views" \
         "$SHARED_PATH/storage/logs"

tar -xzf "$WORK_DIR/$ARCHIVE_ASSET" -C "$release_path"

rm -rf "$release_path/storage"
ln -sfn "$SHARED_PATH/storage" "$release_path/storage"
ln -sfn "$SHARED_PATH/.env" "$release_path/.env"
mkdir -p "$release_path/bootstrap/cache"
chmod -R ug+rwX "$SHARED_PATH/storage" "$release_path/bootstrap/cache" || true

if [ -f "$CURRENT_PATH/artisan" ]; then
  echo "Putting current app into maintenance mode..."
  "$PHP_BIN" "$CURRENT_PATH/artisan" down --refresh=15 --secret="$MAINTENANCE_SECRET" || true
  MAINTENANCE_ENABLED=1
fi

echo "Running Laravel release commands..."
cd "$release_path"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan filament:upgrade --no-interaction || true
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

echo "Switching current symlink..."
ln -sfn "$release_path" "$DEPLOY_PATH/current.tmp"
mv -Tf "$DEPLOY_PATH/current.tmp" "$CURRENT_PATH"

"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan up || true
MAINTENANCE_ENABLED=0

echo "Cleaning old releases..."
(
  cd "$RELEASES_PATH"
  ls -1dt */ 2>/dev/null | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf
)

echo "Deploy complete: $release_name"
