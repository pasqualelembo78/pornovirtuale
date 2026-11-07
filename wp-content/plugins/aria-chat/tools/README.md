# Strumenti editoriali

Copia di sicurezza degli script che girano sul server. I percorsi attivi restano in `/opt/aria/backend/characters/`:

- `editorial_batch.sh` — batch giornaliero, cron alle 09:05 Europe/Rome.
- `gen_one.php` — genera e valida una scheda.
- `make_images.php` — crea le immagini social mancanti.
- `indexnow.sh` — segnala gli URL nuovi a IndexNow.
- `build_ai_index.php` — rigenera `/ai-index.json` per i crawler AI.

Cron:

```
5 9 * * * /bin/bash /opt/aria/backend/characters/editorial_batch.sh >/dev/null 2>&1
```

Dopo una modifica, copiare lo script nella posizione attiva e verificarne la sintassi.
