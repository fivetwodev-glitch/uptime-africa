#!/usr/bin/env bash
# Mise à jour d'Uptime Africa sur Hostinger (Premium, Business ou Cloud). NE PAS lancer sans accord explicite.
# En SSH, depuis le dossier du projet (hors public_html) : bash deploy/hostinger/deploy.sh
# Premier déploiement : voir docs/deploiement-hostinger.md (étapes 1 à 9) avant ce script.
# Variables optionnelles : PHP_BIN (défaut : php), COMPOSER_BIN (défaut : composer2 sinon composer).
set -euo pipefail
cd "$(dirname "$0")/../.."

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer2 || command -v composer || true)}"

[ -f .env ] || { echo "ERREUR : .env de production absent"; exit 1; }
grep -q '^APP_ENV=production' .env || { echo "ERREUR : APP_ENV doit valoir production"; exit 1; }
grep -q '^APP_DEBUG=false' .env || { echo "ERREUR : APP_DEBUG doit valoir false"; exit 1; }
[ -n "$COMPOSER_BIN" ] || { echo "ERREUR : composer introuvable (forfait Premium minimum)"; exit 1; }
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "ERREUR : PHP 8.3+ requis en ligne de commande (définir PHP_BIN avec le chemin indiqué dans hPanel)"; exit 1; }
[ -f public/build/manifest.json ] || { echo "ERREUR : public/build absent (npm run build en local puis envoi)"; exit 1; }

# Sauvegarde de la base avant toute migration.
bash deploy/hostinger/backup.sh

"$PHP_BIN" artisan down --retry=60 || true
trap '"$PHP_BIN" artisan up' EXIT

git pull --ff-only
"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan queue:restart

"$PHP_BIN" artisan up
trap - EXIT
"$PHP_BIN" artisan uptime:preflight --network
