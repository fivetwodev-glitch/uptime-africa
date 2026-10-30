# Lance le contrôle de style puis les tests automatisés.
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

php artisan config:clear
.\vendor\bin\pint --test
if ($LASTEXITCODE -ne 0) { Write-Host "Style non conforme : lancez .\vendor\bin\pint" -ForegroundColor Yellow; exit $LASTEXITCODE }
php artisan test
exit $LASTEXITCODE
