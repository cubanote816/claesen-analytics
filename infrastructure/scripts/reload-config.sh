#!/bin/bash
set -euo pipefail

# Uso: correr en prod-priv-01 después de editar /srv/www/claesen/shared/.env
# a mano, SIN pasar por deploy.sh (deploy.sh ya hace este mismo paso solo,
# como parte del punto 8-9 de un deploy normal).
#
# opcache.validate_timestamps=0 en /etc/php/8.4/fpm/conf.d/10-opcache-prod.ini
# hace que PHP-FPM nunca vuelva a leer archivos en disco por su cuenta —
# config:cache por sí solo NO alcanza, hace falta recargar PHP-FPM para que
# los workers tomen el config nuevo (CLA-232, 2026-07-06: SESSION_DOMAIN
# quedó desincronizado del dominio real y bloqueaba el login de Azure OAuth
# porque el navegador descarta cookies con Domain que no matchea el host).

APP_DIR="/srv/www/claesen"
CURRENT="$APP_DIR/current"

echo "=== Recargando configuración (sin nuevo release) ==="

echo "[1/3] Limpiando y recacheando config..."
cd "$CURRENT"
php artisan config:clear
sudo -u www-data php artisan config:cache

echo "[2/3] Recargando PHP-FPM (flush de opcache)..."
sudo systemctl reload php8.4-fpm

echo "[3/3] Listo."
bash /opt/claesen/scripts/notify.sh "Config recargada" "SESSION_DOMAIN/config .env aplicado + PHP-FPM reload en $(hostname)" "default" "gear"

echo "=== Config recargada y PHP-FPM reiniciado ==="
