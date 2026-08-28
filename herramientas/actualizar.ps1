# =====================================================================
#  CLIMACO POS - Poner el proyecto al dia (Fase 20)
#
#  Uso:
#      cd C:\xampp\htdocs\climacopos
#      powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
# =====================================================================

$ErrorActionPreference = 'Continue'

function Paso($texto) {
    Write-Host ""
    Write-Host "==> $texto" -ForegroundColor Cyan
}

if (-not (Test-Path "artisan")) {
    Write-Host "Ejecuta este script desde la raiz del proyecto (donde esta 'artisan')." -ForegroundColor Red
    exit 1
}

Paso "Limpiando caches y vistas compiladas"
if (Test-Path "storage\framework\views") {
    Remove-Item storage\framework\views\*.php -Force -ErrorAction SilentlyContinue
}
php artisan optimize:clear

Paso "Migraciones de la base central"
php artisan migrate --force

Paso "Migraciones de cada empresa"
php artisan tenants:migrate

Paso "Datos de arranque de cada empresa"
php artisan tenants:seed

Paso "Enlace de storage"
if (-not (Test-Path "public\storage")) {
    php artisan storage:link
} else {
    Write-Host "Ya existe." -ForegroundColor DarkGray
}

Paso "Limpiando caches otra vez"
php artisan optimize:clear

Write-Host ""
Write-Host "Listo." -ForegroundColor Green
Write-Host ""
Write-Host "  Panel  : http://jectan.climacopos.test/panel"
Write-Host "  Portal : http://jectan.climacopos.test"
Write-Host ""
Write-Host "RECUERDA: registra el middleware 'agente' en bootstrap/app.php (ver README)
y recarga con Ctrl+Shift+R la primera vez" -ForegroundColor Yellow
Write-Host "para que coja el CSS y el JavaScript nuevos."
