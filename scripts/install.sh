#!/usr/bin/env bash
# Installation locale (Linux / macOS / WSL / Git Bash)
set -euo pipefail
cd "$(dirname "$0")/.."

for cmd in php composer node npm; do
  command -v "$cmd" >/dev/null || { echo "ERREUR : $cmd introuvable"; exit 1; }
done
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);" || { echo "ERREUR : PHP 8.3 minimum"; exit 1; }

composer install --no-interaction
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --ansi
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force
npm install --no-audit --no-fund
npm run build
php artisan test
echo "Installation terminée. Lancez : ./scripts/start.sh"
