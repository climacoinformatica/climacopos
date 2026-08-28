#!/usr/bin/env bash
#
# Saca el bloque «Al terminar un cobro» fuera del formulario del terminal.
#
# En HTML los formularios NO se pueden anidar: el navegador los fusiona y
# manda todo junto, por eso al guardar el ajuste del salon se validaba
# tambien el del terminal y saltaba «The teclado tactil field is required».

set -euo pipefail

F="/var/www/climacopos/resources/views/panel/ajustes/hardware.blade.php"

cp "$F" "${F}.bak"
echo "Copia en ${F}.bak"

python3 - "$F" <<'PYEOF'
import sys

ruta = sys.argv[1]
lineas = open(ruta, encoding='utf-8').read().split('\n')

# --- Localizar el bloque, por contenido y no por numero de linea
inicio = None
for i, l in enumerate(lineas):
    if 'Al terminar un cobro' in l and '<h3' in l:
        inicio = i
        break

if inicio is None:
    print('El bloque ya no esta ahi: nada que mover.')
    sys.exit(0)

# Termina justo antes del siguiente <h3>
fin = None
for i in range(inicio + 1, len(lineas)):
    if '<h3' in lineas[i]:
        fin = i
        break

if fin is None:
    print('No encuentro donde acaba el bloque. Sin tocar nada.')
    sys.exit(1)

bloque = lineas[inicio:fin]

# --- Fuera de donde esta
lineas = lineas[:inicio] + lineas[fin:]

# --- El </form> del terminal, que es el primero tras el bloque
cierre = None
for i, l in enumerate(lineas):
    if l.strip() == '</form>':
        cierre = i
        break

if cierre is None:
    print('No encuentro el cierre del formulario. Sin tocar nada.')
    sys.exit(1)

# --- Se reconstruye como tarjeta propia, fuera del formulario
nuevo = [
    '',
    '{{--',
    '    Este ajuste va en su PROPIA tarjeta y su propio formulario.',
    '',
    '    Estaba dentro del formulario del terminal, y en HTML los',
    '    formularios no se pueden anidar: el navegador los fusiona y manda',
    '    todo junto, asi que al guardar esto se validaba tambien el del',
    '    terminal y saltaba «The teclado tactil field is required».',
    '--}}',
    '<div class="tarjeta" style="max-width:640px">',
    '    <h2>Al terminar un cobro</h2>',
    '',
    '    <form method="POST" action="{{ route(\'panel.ajustes.salon\') }}">',
    '        @csrf',
    '',
    '        <div class="campo">',
    '            <label for="trasCobrar">Qué hacer después de cobrar</label>',
    '            <select name="tras_cobrar" id="trasCobrar">',
    '                @foreach ([',
    '                    \'NADA\'     => \'Quedarse en el punto de venta\',',
    '                    \'SELECTOR\' => \'Volver a elegir usuario\',',
    '                    \'INICIO\'   => \'Volver al menú principal\',',
    '                ] as $clave => $texto)',
    '                    <option value="{{ $clave }}"',
    '                            @selected((tenant(\'tras_cobrar\') ?: \'NADA\') === $clave)>',
    '                        {{ $texto }}',
    '                    </option>',
    '                @endforeach',
    '            </select>',
    '',
    '            <p class="campo__pista">',
    '                Con un solo ordenador y varios profesionales que cobran lo',
    '                suyo, <strong>«volver a elegir usuario»</strong> hace que cada',
    '                uno meta su PIN y lo que teclee se le asigne solo. En un salón',
    '                con recepción, donde una persona cobra todo el día, déjalo en',
    '                «quedarse en el punto de venta».',
    '            </p>',
    '            <p class="campo__pista">',
    '                Este ajuste es del salón entero, no de este terminal.',
    '            </p>',
    '        </div>',
    '',
    '        <button type="submit" class="boton boton--pequeno">Guardar</button>',
    '    </form>',
    '</div>',
]

lineas = lineas[:cierre + 1] + nuevo + lineas[cierre + 1:]

open(ruta, 'w', encoding='utf-8').write('\n'.join(lineas))
print('Bloque movido fuera del formulario del terminal.')
PYEOF

echo
grep -c "trasCobrarOculto" "$F" || true
echo "(si sale 0, el campo oculto ya no hace falta y se ha ido con el bloque)"
