#!/usr/bin/env bash
#
# =====================================================================
#  Compila el conector para Windows
#
#  ABORTA SI FALLA. La version anterior seguia como si nada y dejaba el
#  ejecutable ANTERIOR en su sitio: se descargaba, se instalaba, y salia
#  el comportamiento viejo sin que nada avisara. Nos costo una hora.
# =====================================================================

set -euo pipefail

DESTINO="/var/www/climacopos/storage/app/conector"

if ! command -v go &>/dev/null; then
    echo "Falta Go:  sudo apt install -y golang-go"
    exit 1
fi

cd "$(dirname "$0")/agente-go"

mkdir -p "$DESTINO"

echo "==> Compilando para Windows de 64 bits"

if ! GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build \
        -ldflags="-s -w -H=windowsgui" \
        -o "${DESTINO}/climaco-conector.exe.nuevo" \
        ./cmd/climaco-agente; then

    echo
    echo "  ############################################"
    echo "  #  LA COMPILACION HA FALLADO               #"
    echo "  #                                          #"
    echo "  #  El ejecutable anterior sigue en su      #"
    echo "  #  sitio. NO lo descargues: seria el viejo #"
    echo "  ############################################"
    echo

    rm -f "${DESTINO}/climaco-conector.exe.nuevo"
    exit 1
fi

# Solo se sustituye si la compilacion fue bien
mv "${DESTINO}/climaco-conector.exe.nuevo" "${DESTINO}/climaco-conector.exe"
chmod 644 "${DESTINO}/climaco-conector.exe"

echo
echo "Listo: ${DESTINO}/climaco-conector.exe"
ls -lh "${DESTINO}/climaco-conector.exe"
