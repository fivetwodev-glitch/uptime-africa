#!/usr/bin/env bash
# Déploiement sur Hostinger mutualisé — NE PAS EXÉCUTER avant la phase de mise en production.
# À lancer en SSH sur le serveur, depuis le dossier du projet (hors public_html).
# Prérequis : .env de production rempli à partir de .env.production.example.
set -euo pipefail
cd "$(dirname "$0")/../.."

[ -f .env ] || { echo "ERREUR : .env de production absent"; exit 1; }
grep -q '^APP_ENV=production' .env || { echo "ERREUR : APP_ENV doit valoir production"; exit 1; }
grep -q '^APP_DEBUG=false' .env || { echo "ERREUR : APP_DEBUG doit valoir false"; exit 1; }

php artisan down --retry=60
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan up

echo "Déploiement terminé. Les assets (public/build) sont compilés en local ou en CI, pas sur le serveur."
