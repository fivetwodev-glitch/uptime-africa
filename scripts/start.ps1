# Démarre l'environnement de développement (Windows / PowerShell)
# Serveur web + file d'attente + planificateur + Vite, dans une seule fenêtre.
# Usage : powershell -ExecutionPolicy Bypass -File scripts\start.ps1
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

if (-not (Test-Path .env)) {
    Write-Host "ERREUR : .env absent. Lancez d'abord scripts\install.ps1" -ForegroundColor Red
    exit 1
}

php artisan migrate --force
Write-Host "Application : http://127.0.0.1:8000  (Ctrl+C pour arrêter)" -ForegroundColor Green
composer run dev
