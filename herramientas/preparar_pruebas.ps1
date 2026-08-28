# =====================================================================
#  CLIMACO POS - Preparar la base de datos de pruebas
# =====================================================================
#
#  Las pruebas usan una base de datos aparte para no tocar la real.
#  Este script:
#
#    1. Genera .env.testing a partir de tu .env
#    2. Crea la base de datos de pruebas si no existe
#    3. Aplica todas las migraciones
#
#  Hay que volver a ejecutarlo cada vez que se anaden migraciones.
# =====================================================================

$ErrorActionPreference = 'Stop'

$baseTest = 'climacopos_central_testing'
$mysql    = 'C:\xampp\mysql\bin\mysql.exe'

function Paso($texto) {
    Write-Host ""
    Write-Host "==> $texto" -ForegroundColor Cyan
}

if (-not (Test-Path "artisan")) {
    Write-Host "Ejecuta este script desde la raiz del proyecto." -ForegroundColor Red
    exit 1
}

if (-not (Test-Path ".env")) {
    Write-Host "No encuentro el fichero .env" -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------
Paso "Generando .env.testing"

$lineas = Get-Content ".env" -Encoding UTF8
$salida = @()
$vistas = @{}

foreach ($linea in $lineas) {
    if ($linea -match '^\s*APP_ENV\s*=') {
        $salida += 'APP_ENV=testing'
        $vistas['APP_ENV'] = $true
    }
    elseif ($linea -match '^\s*DB_DATABASE\s*=') {
        $salida += "DB_DATABASE=$baseTest"
        $vistas['DB_DATABASE'] = $true
    }
    elseif ($linea -match '^\s*APP_DEBUG\s*=') {
        $salida += 'APP_DEBUG=true'
        $vistas['APP_DEBUG'] = $true
    }
    elseif ($linea -match '^\s*MAIL_MAILER\s*=') {
        # En pruebas nunca se envia correo de verdad
        $salida += 'MAIL_MAILER=array'
        $vistas['MAIL_MAILER'] = $true
    }
    else {
        $salida += $linea
    }
}

if (-not $vistas['APP_ENV'])      { $salida += 'APP_ENV=testing' }
if (-not $vistas['DB_DATABASE'])  { $salida += "DB_DATABASE=$baseTest" }
if (-not $vistas['MAIL_MAILER'])  { $salida += 'MAIL_MAILER=array' }

$salida | Set-Content ".env.testing" -Encoding UTF8

Write-Host "  .env.testing generado con DB_DATABASE=$baseTest" -ForegroundColor DarkGray

# ---------------------------------------------------------------------
Paso "Creando la base de datos de pruebas"

if (Test-Path $mysql) {
    $sql = "CREATE DATABASE IF NOT EXISTS ``$baseTest`` " +
           "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    & $mysql -u root -e $sql

    if ($LASTEXITCODE -eq 0) {
        Write-Host "  Base '$baseTest' lista." -ForegroundColor DarkGray
    } else {
        Write-Host "  No se pudo crear. Creala a mano en phpMyAdmin." -ForegroundColor Yellow
    }
} else {
    Write-Host "  No encuentro mysql.exe en $mysql" -ForegroundColor Yellow
    Write-Host "  Crea la base '$baseTest' a mano en phpMyAdmin." -ForegroundColor Yellow
}

# ---------------------------------------------------------------------
Paso "Aplicando migraciones a la base de pruebas"

php artisan config:clear
php artisan migrate --env=testing --force

# ---------------------------------------------------------------------
Paso "Limpiando bases de tenants huerfanas"

# Cada prueba crea y borra su empresa, pero si alguna se corta a medias
# deja la base colgada. Se limpian las que empiecen por el prefijo.
if (Test-Path $mysql) {
    $consulta = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA " +
                "WHERE SCHEMA_NAME LIKE 'climacopos\_emp\_%';"

    $bases = & $mysql -u root -N -B -e $consulta

    $huerfanas = 0

    foreach ($base in $bases) {
        $base = $base.Trim()
        if (-not $base) { continue }

        # Solo se borran las que no figuran en la base central real
        $existe = & $mysql -u root -N -B -e `
            "SELECT COUNT(*) FROM climacopos_central.empresas WHERE tenancy_db_name='$base';" 2>$null

        if ($existe -eq '0') {
            & $mysql -u root -e "DROP DATABASE IF EXISTS ``$base``;"
            $huerfanas++
        }
    }

    Write-Host "  $huerfanas base(s) huerfana(s) eliminada(s)." -ForegroundColor DarkGray
}

php artisan config:clear

Write-Host ""
Write-Host "Listo. Ya puedes ejecutar:" -ForegroundColor Green
Write-Host ""
Write-Host "  php artisan test"
Write-Host "  php artisan test --filter=CorreosTest"
Write-Host ""
