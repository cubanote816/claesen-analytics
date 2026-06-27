#!/bin/bash
set -euo pipefail
source /etc/claesen-notify.env

SERVICES=(nginx php8.3-fpm mysql redis-server meilisearch supervisor)
FAILED=()

for SVC in "${SERVICES[@]}"; do
    if ! systemctl is-active --quiet "$SVC"; then
        FAILED+=("$SVC")
    fi
done

if [ ${#FAILED[@]} -gt 0 ]; then
    LIST=$(printf '%s\n' "${FAILED[@]}")
    bash /opt/claesen/scripts/notify.sh \
        "SERVICIO CAIDO" \
        "Servicios inactivos en $(hostname):\n$LIST" \
        "max" \
        "rotating_light,x"
    echo "[$(date)] ALERTA: servicios caidos: ${FAILED[*]}"
fi

# Disco
DISK_USE=$(df / --output=pcent | tail -1 | tr -d ' %')
if [ "$DISK_USE" -ge 85 ]; then
    bash /opt/claesen/scripts/notify.sh \
        "DISCO LLENO ${DISK_USE}%" \
        "Disco raiz al ${DISK_USE}% en $(hostname). Limpiar logs o ampliar volumen." \
        "high" \
        "floppy_disk,warning"
    echo "[$(date)] ALERTA: disco al ${DISK_USE}%"
fi

# Memoria
MEM_FREE=$(free | awk '/^Mem:/{printf "%d", $4/$2 * 100}')
if [ "$MEM_FREE" -lt 10 ]; then
    bash /opt/claesen/scripts/notify.sh \
        "MEMORIA BAJA ${MEM_FREE}% libre" \
        "Memoria libre al ${MEM_FREE}% en $(hostname)." \
        "high" \
        "brain,warning"
    echo "[$(date)] ALERTA: memoria libre ${MEM_FREE}%"
fi

echo "[$(date)] Monitor OK — disco:${DISK_USE}% mem_libre:${MEM_FREE}%"
