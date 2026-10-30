# Installation locale d'Uptime Africa (Windows / PowerShell)
# Usage : powershell -ExecutionPolicy Bypass -File scripts\install.ps1
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

function Assert-Command($name, $hint) {
    if (-not (Get-Command $name -ErrorAction SilentlyContinue)) {
        Write-Host "ERREUR : '$name' introuvable. $hint" -ForegroundColor Red
        exit 1
    }
}

Write-Host "== Vérification des prérequis ==" -ForegroundColor Cyan
Assert-Command php 'Installez Laravel Herd (https://herd.laravel.com/windows) ou PHP 8.3.'
Assert-Command composer 'Composer est fourni par Herd, sinon https://getcomposer.org.'
Assert-Command node 'Installez Node.js 22 LTS (https://nodejs.org).'
Assert-Command npm 'npm est fourni avec Node.js.'

$phpOk = php -r "echo version_compare(PHP_VERSION, '8.3.0', '>=') ? '1' : '0';"
if ($phpOk -ne '1') { Write-Host "ERREUR : PHP 8.3 minimum requis." -ForegroundColor Red; exit 1 }

foreach ($ext in 'pdo_sqlite','mbstring','openssl','curl','fileinfo') {
    $loaded = php -r "echo extension_loaded('$ext') ? '1' : '0';"
    if ($loaded -ne '1') { Write-Host "ERREUR : extension PHP '$ext' manquante (activez-la dans php.ini)." -ForegroundColor Red; exit 1 }
}

Write-Host "== Dépendances PHP ==" -ForegroundColor Cyan
composer install --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    Write-Host ".env créé à partir de .env.example"
}

$hasKey = Select-String -Path .env -Pattern '^APP_KEY=base64:' -Quiet
if (-not $hasKey) { php artisan key:generate --ansi }

if (-not (Test-Path database\database.sqlite)) {
    New-Item -ItemType File database\database.sqlite | Out-Null
}

Write-Host "== Base de données ==" -ForegroundColor Cyan
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "== Dépendances frontend ==" -ForegroundColor Cyan
npm install --no-audit --no-fund
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
npm run build
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "== Tests ==" -ForegroundColor Cyan
php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ""
Write-Host "Installation terminée. Lancez : powershell -ExecutionPolicy Bypass -File scripts\start.ps1" -ForegroundColor Green
