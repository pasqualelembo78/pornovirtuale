#!/bin/bash
# Invia a IndexNow (Bing, Yandex, Seznam) gli URL appena editorializzati.
set -u
LIST="$1"
KEY=$(cat /opt/aria/backend/characters/indexnow.key)
[ -s "$LIST" ] || exit 0
URLS=$(jq -R . <"$LIST" | jq -s '.[0:1000]')
curl -s --max-time 30 -X POST 'https://api.indexnow.org/indexnow' \
  -H 'Content-Type: application/json' \
  --data "{\"host\":\"pornovirtuale.com\",\"key\":\"$KEY\",\"keyLocation\":\"https://pornovirtuale.com/$KEY.txt\",\"urlList\":$URLS}"
