#!/usr/bin/env bash
# Sauvegarde MySQL quotidienne (cron) : bash deploy/hostinger/backup.sh
# Fichiers : ~/sauvegardes/uptime-africa/AAAA-MM-JJ_HHMM.sql.gz (droits 600), conservés BACKUP_KEEP_DAYS jours (défaut 14).
# Le mot de passe n'apparaît ni dans la ligne de commande ni dans les journaux (fichier d'options temporaire).
set -euo pipefail
cd "$(dirname "$0")/../.."

env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }
DIR="${BACKUP_DIR:-$HOME/sauvegardes/uptime-africa}"
KEEP="${BACKUP_KEEP_DAYS:-14}"
umask 077
mkdir -p "$DIR"

OPTS="$(mktemp)"
trap 'rm -f "$OPTS"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' \
  "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" "$(env_value DB_HOST)" "$(env_value DB_PORT)" > "$OPTS"

FILE="$DIR/$(date +%Y-%m-%d_%H%M).sql.gz"
mysqldump --defaults-extra-file="$OPTS" --single-transaction --quick --routines --no-tablespaces "$(env_value DB_DATABASE)" | gzip > "$FILE"
[ -s "$FILE" ] || { echo "ERREUR : sauvegarde vide"; rm -f "$FILE"; exit 1; }
find "$DIR" -name '*.sql.gz' -mtime +"$KEEP" -delete
echo "Sauvegarde : $FILE"
