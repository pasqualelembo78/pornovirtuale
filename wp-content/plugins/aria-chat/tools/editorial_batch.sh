#!/bin/bash
# Editorializzazione automatica giornaliera delle pagine personaggio.
export PATH=/usr/local/bin:/usr/bin:/bin
# Gira da cron dopo il reset della quota Gemini (09:05 Europe/Rome).
# Cap giornaliero prudente, un solo worker, stop se la quota si esaurisce.

set -u

DIR=/opt/aria/backend/characters
EDIT="$DIR/editorial"
LOG="$DIR/editorial_batch.log"
LOCK=/var/lock/editorial_batch.lock
CAP="${CAP:-800}"
export CAP
CONSECUTIVE_MAX="${CONSECUTIVE_MAX:-6}"
MODEL="${GEN_MODEL:-gemini-3.1-flash-lite}"
LIST=$(mktemp /tmp/editorial_batch.XXXXXX)
INDEX_LIST=$(mktemp /tmp/editorial_index.XXXXXX)

exec 9>"$LOCK"
flock -n 9 || { echo "$(date -Is) altro batch già in corso" >>"$LOG"; exit 0; }

mkdir -p "$EDIT"
echo "$(date -Is) avvio batch (cap $CAP, modello $MODEL)" >>"$LOG"

# Costruisce la lista a rotazione tra categorie, in ordine di completezza,
# saltando i personaggi già editorializzati.
php -r '
$dir = "/opt/aria/backend/characters/data";
$edit = "/opt/aria/backend/characters/editorial";
$cap = (int) getenv("CAP");
$cats = array();
foreach (glob($dir . "/*.json") as $f) {
    if (basename($f) === "categories.json") continue;
    $cat = basename($f, ".json");
    $rows = json_decode((string) file_get_contents($f), true) ?: array();
    usort($rows, function ($a, $b) {
        $qa = (!empty($a["job"]) ? 2 : 0) + (strlen((string) ($a["personality"] ?? "")) >= 90 ? 1 : 0);
        $qb = (!empty($b["job"]) ? 2 : 0) + (strlen((string) ($b["personality"] ?? "")) >= 90 ? 1 : 0);
        return $qb <=> $qa;
    });
    $miss = array();
    foreach ($rows as $c) {
        if (empty($c["slug"])) continue;
        $file = $edit . "/" . $cat . "/" . $c["slug"] . ".json";
        if (!is_file($file) || filesize($file) <= 300) $miss[] = $c["slug"];
    }
    if ($miss) $cats[$cat] = $miss;
}
$n = 0; $idx = array_fill_keys(array_keys($cats), 0);
while ($n < $cap) {
    $added = false;
    foreach ($cats as $cat => $slugs) {
        if (!isset($slugs[$idx[$cat]])) continue;
        echo $cat . " " . $slugs[$idx[$cat]] . "\n";
        $idx[$cat]++;
        $n++;
        $added = true;
        if ($n >= $cap) break;
    }
    if (!$added) break;
}
' >"$LIST"

TOT=$(wc -l <"$LIST")
echo "$(date -Is) personaggi in lista: $TOT" >>"$LOG"

if [ "${DRY:-0}" = "1" ]; then cat "$LIST"; rm -f "$LIST" "$INDEX_LIST"; exit 0; fi

OK=0
KO=0
CONSEC=0
while read -r CAT SLUG; do
    [ -z "${CAT:-}" ] && continue
    if GEN_MODEL="$MODEL" php /opt/aria/backend/characters/gen_one.php "$CAT" "$SLUG" >>"$LOG" 2>&1; then
        OK=$((OK + 1))
        php /opt/aria/backend/characters/make_images.php "$CAT" "$SLUG" >>"$LOG" 2>&1
        echo "https://pornovirtuale.com/stanza/$CAT/$SLUG/" >>"$INDEX_LIST"
        CONSEC=0
    else
        KO=$((KO + 1))
        CONSEC=$((CONSEC + 1))
        if [ "$CONSEC" -ge "$CONSECUTIVE_MAX" ]; then
            echo "$(date -Is) stop: $CONSEC errori consecutivi (quota esaurita)" >>"$LOG"
            break
        fi
    fi
    sleep 3
done <"$LIST"

# Immagini social per le pagine appena editorializzate (solo quelle mancanti).
php /opt/aria/backend/characters/make_images.php >>"$LOG" 2>&1

# Segnala a IndexNow gli URL nuovi (Bing, Yandex, Seznam).
if [ -s "$INDEX_LIST" ]; then
    echo "$(date -Is) indexnow: $(wc -l <"$INDEX_LIST") URL" >>"$LOG"
    /opt/aria/backend/characters/indexnow.sh "$INDEX_LIST" >>"$LOG" 2>&1 || true
fi

rm -f "$LIST" "$INDEX_LIST"
echo "$(date -Is) fine batch: ok=$OK ko=$KO totali=$(find "$EDIT" -name '*.json' | wc -l)" >>"$LOG"
