#!/bin/bash
set -euo pipefail
source /etc/claesen-backup.env

DATE=$(date +%Y-%m-%d_%H%M)
DEST="/var/backups/claesen/mysql/${DATE}_${MYSQL_DB}.sql.gz"

mysqldump \
  -h "$MYSQL_HOST" \
  -u "$MYSQL_USER" \
  -p"$MYSQL_PASS" \
  --single-transaction \
  --no-tablespaces \
  --routines \
  --triggers \
  "$MYSQL_DB" | gzip -9 > "$DEST"

echo "[$(date)] MySQL backup OK: $DEST ($(du -sh "$DEST" | cut -f1))"

# Limpiar backups MySQL más antiguos de 7 días
find /var/backups/claesen/mysql -name "*.sql.gz" -mtime +7 -delete
