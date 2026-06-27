#!/bin/bash
# notify.sh TITLE MESSAGE [PRIORITY] [TAGS]
# Priority: max, high, default, low, min
source /etc/claesen-notify.env

TITLE="${1:-Alert}"
MSG="${2:-Sin mensaje}"
PRIORITY="${3:-default}"
TAGS="${4:-bell}"
HOST=$(hostname)

curl -s \
  -H "Title: [${HOST}] ${TITLE}" \
  -H "Priority: ${PRIORITY}" \
  -H "Tags: ${TAGS}" \
  -d "${MSG}" \
  "${NTFY_SERVER}/${NTFY_TOPIC}" > /dev/null 2>&1 || true
