#!/bin/bash
LOG=/var/log/claesen-backup.log
exec >> "$LOG" 2>&1

echo "===== BACKUP INICIO $(date) ====="

if bash /opt/claesen/scripts/backup-mysql.sh && \
   bash /opt/claesen/scripts/backup-files.sh; then
    SNAP=$(du -sh /var/backups/restic/claesen/ | cut -f1)
    SQL=$(ls -lh /var/backups/claesen/mysql/*.sql.gz 2>/dev/null | tail -1 | awk '{print $5}')
    bash /opt/claesen/scripts/notify.sh \
        "Backup OK" \
        "Backup completado en $(hostname)\nRestic: ${SNAP}\nMySQL: ${SQL}" \
        "low" \
        "white_check_mark,floppy_disk"
else
    bash /opt/claesen/scripts/notify.sh \
        "BACKUP FALLIDO" \
        "El backup falló en $(hostname). Revisar $LOG" \
        "max" \
        "rotating_light,floppy_disk"
fi

echo "===== BACKUP FIN $(date) ====="
