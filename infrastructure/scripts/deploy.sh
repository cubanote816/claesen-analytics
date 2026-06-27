#!/bin/bash
set -euo pipefail

APP_DIR="/srv/www/claesen"
SHARED_DIR="$APP_DIR/shared"
RELEASES_DIR="$APP_DIR/releases"
KEEP_RELEASES=3
TIMESTAMP=$(date +%Y%m%d%H%M%S)
RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"
REPO="https://github.com/cubanote816/claesen-analytics.git"
BRANCH="${1:-main}"
CURRENT="$APP_DIR/current"
ARTISAN="php $CURRENT/artisan"

echo "=== DEPLOY $TIMESTAMP — branch: $BRANCH ==="

# ── 0. BACKUP PRE-DEPLOY ─────────────────────
echo "[ 0/10] Backup preventivo antes de migrar..."
bash /opt/claesen/scripts/backup-mysql.sh   && echo "  Backup OK"   || echo "  ADVERTENCIA: backup falló — continuando de todas formas"

# ── 1. MODO MANTENIMIENTO ────────────────────
echo "[ 1/10] Entrando en modo mantenimiento..."
if [ -L "$CURRENT" ] && [ -f "$CURRENT/artisan" ]; then
    $ARTISAN down --refresh=15 --secret="claesen-update-$(date +%Y%m%d)" || true
fi

# ── 2. CLONAR RELEASE ────────────────────────
echo "[ 2/10] Clonando repo (branch: $BRANCH)..."
git clone --depth=1 --branch "$BRANCH" "$REPO" "$RELEASE_DIR"

# ── 3. ENLAZAR SHARED FILES ──────────────────
echo "[ 3/10] Enlazando shared files..."
rm -rf "$RELEASE_DIR/storage"
ln -nfs "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
ln -nfs "$SHARED_DIR/.env"    "$RELEASE_DIR/.env"
# Garantizar que www-data puede leer .env como fallback si no hay config cache
sudo chown bert:www-data "$SHARED_DIR/.env"
sudo chmod 640 "$SHARED_DIR/.env"

# ── 4. DEPENDENCIAS PHP ──────────────────────
echo "[ 4/10] Composer install..."
cd "$RELEASE_DIR"
composer install --optimize-autoloader --no-dev --no-interaction

# ── 5. BUILD FRONTEND (VITE) ─────────────────
echo "[ 5/10] npm build..."
npm ci --prefer-offline
npm run build
rm -rf node_modules

# ── 6. MIGRACIONES ───────────────────────────
echo "[ 6/10] Migraciones..."
php artisan migrate --force

# ── 7. ASSETS Y CACHE ────────────────────────
echo "[ 7/10] Filament + cache..."
php artisan optimize:clear
php artisan filament:upgrade --no-interaction 2>/dev/null || true
php artisan optimize
php artisan storage:link 2>/dev/null || true

# ── 8. PERMISOS ──────────────────────────────
echo "[ 8/10] Permisos..."
sudo chown -R www-data:www-data "$RELEASE_DIR"
sudo chmod -R 775 "$RELEASE_DIR"
sudo chown -R www-data:www-data "$SHARED_DIR/storage"
sudo chmod -R 775 "$SHARED_DIR/storage"
# Regenerar config cache post-chown para que www-data sea propietario
sudo -u www-data php "$RELEASE_DIR/artisan" config:cache

# ── 9. ACTIVAR RELEASE ───────────────────────
echo "[ 9/10] Activando release..."
sudo rm -rf "$APP_DIR/current" && sudo ln -s "$RELEASE_DIR" "$APP_DIR/current"
sudo systemctl reload php8.4-fpm

# Reiniciar colas de forma elegante
php "$APP_DIR/current/artisan" queue:restart || true
sudo supervisorctl start claesen-worker:*   2>/dev/null || sudo supervisorctl restart claesen-worker:* 2>/dev/null || true
sudo supervisorctl start claesen-scheduler  2>/dev/null || sudo supervisorctl restart claesen-scheduler 2>/dev/null || true

# ── 10. SALIR DE MANTENIMIENTO ───────────────
echo "[10/10] Saliendo de mantenimiento..."
php "$APP_DIR/current/artisan" up

# Limpiar releases antiguas
ls -dt "$RELEASES_DIR"/*/  2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs sudo rm -rf 2>/dev/null || true

echo "=== DEPLOY $TIMESTAMP COMPLETADO ==="

bash /opt/claesen/scripts/notify.sh "Deploy OK" "Deploy ${TIMESTAMP} completado en $(hostname)\nBranch: ${BRANCH}" "default" "rocket,white_check_mark"
