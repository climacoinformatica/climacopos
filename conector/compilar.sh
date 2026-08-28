#!/usr/bin/env bash
#
# =====================================================================
#  Compila el conector para Windows
#
#  Se ejecuta EN EL SERVIDOR, aunque el programa sea para Windows: Go
#  compila para cualquier sistema desde cualquier sistema, que es la
#  razon principal de haberlo elegido.
# =====================================================================

set -euo pipefail

DESTINO="/var/www/climacopos/storage/app/conector"

if ! command -v go &>/dev/null; then
    echo "Falta Go. Instalalo con:"
    echo
    echo "  sudo apt install -y golang-go"
    echo
    exit 1
fi

cd "$(dirname "$0")/agente-go"

mkdir -p "$DESTINO"

echo "==> Compilando para Windows de 64 bits"

# -H=windowsgui evita que se abra una ventana negra de consola al
# arrancar el servicio, que asustaria al cliente cada vez que enciende
# el ordenador
GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build \
    -ldflags="-s -w -H=windowsgui" \
    -o "${DESTINO}/climaco-conector.exe" \
    ./cmd/climaco-agente

chmod 644 "${DESTINO}/climaco-conector.exe"

echo
echo "Listo: ${DESTINO}/climaco-conector.exe"
ls -lh "${DESTINO}/climaco-conector.exe"

cat <<'FIN'

  El fichero es UNO SOLO para todos los salones. Al descargarlo desde el
  panel, el servidor le anade al final la direccion del salon y su token.

  No hace falta recompilar al dar de alta un cliente nuevo.

FIN
