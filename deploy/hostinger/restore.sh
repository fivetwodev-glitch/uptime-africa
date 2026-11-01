#!/usr/bin/env bash
# Restauration d'une sauvegarde : bash deploy/hostinger/restore.sh ~/sauvegardes/uptime-africa/AAAA-MM-JJ_HHMM.sql.gz
# REMPLACE le contenu actuel de la base. Une sauvegarde de l'état actuel est faite avant.
set -euo pipefail
cd "$(dirname "$0")/../.."
FILE="${1:?Usage : restore.sh <fichier .sql.gz>}"
[ -s "$FILE" ] || { echo "ERREUR : fichier introuvable ou vide"; exit 1; }
PHP_BIN="${PHP_BIN:-php}"

read -r -p "Remplacer la base de production par $FILE ? Tapez RESTAURER : " answer
[ "$answer" = "RESTAURER" ] || { echo "Annulé."; exit 1; }

env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }
bash deploy/hostinger/backup.sh
OPTS="$(mktemp)"
trap 'rm -f "$OPTS"; "$PHP_BIN" artisan up' EXIT
umask 077
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' \
  "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" "$(env_value DB_HOST)" "$(env_value DB_PORT)" > "$OPTS"

"$PHP_BIN" artisan down --retry=60 || true
gunzip -c "$FILE" | mysql --defaults-extra-file="$OPTS" "$(env_value DB_DATABASE)"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
echo "Restauration terminée."
