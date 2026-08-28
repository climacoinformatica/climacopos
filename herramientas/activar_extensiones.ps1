# =====================================================================
#  CLIMACO POS - Activar extensiones de PHP necesarias
#
#  Uso (PowerShell como administrador):
#      cd C:\xampp\htdocs\climacopos
#      powershell -ExecutionPolicy Bypass -File herramientas\activar_extensiones.ps1
#
#  Hace una copia de seguridad de php.ini antes de tocar nada.
# =====================================================================

$phpIni = "C:\xampp\php\php.ini"

if (-not (Test-Path $phpIni)) {
    Write-Host "No encuentro $phpIni" -ForegroundColor Red
    Write-Host "Edita la ruta al principio de este script si tu XAMPP esta en otro sitio."
    exit 1
}

# --- Copia de seguridad
$copia = "$phpIni.backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
Copy-Item $phpIni $copia
Write-Host "Copia de seguridad: $copia" -ForegroundColor DarkGray

# --- Extensiones que necesita la aplicacion
$extensiones = @(
    'gd',         # redimensionado de fotos de articulos y clientes
    'exif',       # orientacion de fotos hechas con movil
    'fileinfo',   # deteccion del tipo real de fichero subido
    'mbstring',
    'openssl',
    'pdo_mysql',
    'curl',
    'zip',
    'intl'
)

$contenido = Get-Content $phpIni
$cambios = 0

foreach ($ext in $extensiones) {
    $activa = $contenido | Where-Object { $_ -match "^\s*extension\s*=\s*$ext\s*$" }

    if ($activa) {
        Write-Host "  [ya activa]  $ext" -ForegroundColor DarkGray
        continue
    }

    $comentada = $contenido | Where-Object { $_ -match "^\s*;\s*extension\s*=\s*$ext\s*$" }

    if ($comentada) {
        $contenido = $contenido -replace "^\s*;\s*extension\s*=\s*$ext\s*$", "extension=$ext"
        Write-Host "  [ACTIVADA]   $ext" -ForegroundColor Green
        $cambios++
    } else {
        # No aparece en el fichero: se anade al final
        $contenido += "extension=$ext"
        Write-Host "  [ANADIDA]    $ext" -ForegroundColor Yellow
        $cambios++
    }
}

# --- Limites, para fotos grandes de movil
$limites = @{
    'memory_limit'        = '512M'
    'upload_max_filesize' = '16M'
    'post_max_size'       = '32M'
    'max_execution_time'  = '120'
    'max_file_uploads'    = '30'
}

foreach ($clave in $limites.Keys) {
    $valor = $limites[$clave]

    if ($contenido | Where-Object { $_ -match "^\s*$clave\s*=" }) {
        $contenido = $contenido -replace "^\s*$clave\s*=.*$", "$clave = $valor"
        Write-Host "  [ajustado]   $clave = $valor" -ForegroundColor DarkGray
    }
}

Set-Content -Path $phpIni -Value $contenido -Encoding ASCII

Write-Host ""
if ($cambios -gt 0) {
    Write-Host "$cambios extension(es) activada(s)." -ForegroundColor Green
} else {
    Write-Host "No hubo que activar nada." -ForegroundColor Green
}

Write-Host ""
Write-Host "IMPORTANTE: reinicia Apache desde el panel de XAMPP (Stop y Start)." -ForegroundColor Yellow
Write-Host "Despues comprueba con:  php -m | Select-String 'gd|exif|fileinfo'"
