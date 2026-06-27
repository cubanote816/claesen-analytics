#!/bin/bash
set -euo pipefail
source /etc/claesen-backup.env

export RESTIC_REPOSITORY RESTIC_PASSWORD

restic backup \
  /srv/www/claesen \
  /etc/nginx \
  /etc/ssl/claesen \
  /etc/meilisearch.env \
  /etc/claesen-backup.env \
  --exclude "/srv/www/claesen/*/releases/*/node_modules" \
  --exclude "/srv/www/claesen/*/releases/*/vendor" \
  --tag claesen \
  --verbose 2>&1

restic forget \
  --keep-daily   "$BACKUP_KEEP_DAILY" \
  --keep-weekly  "$BACKUP_KEEP_WEEKLY" \
  --keep-monthly "$BACKUP_KEEP_MONTHLY" \
  --prune \
  --verbose 2>&1

echo "[$(date)] Restic backup + prune OK"
