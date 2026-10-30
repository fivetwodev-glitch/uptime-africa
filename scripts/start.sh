#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] || { echo "ERREUR : .env absent, lancez ./scripts/install.sh"; exit 1; }
php artisan migrate --force
echo "Application : http://127.0.0.1:8000"
composer run dev
