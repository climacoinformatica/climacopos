#!/usr/bin/env bash
#
# Mueve guardarSalon() a DENTRO de la clase.
#
# Se colo entre los `use` y la declaracion de la clase, y PHP no admite
# metodos sueltos fuera de una clase: por eso reventaba el fichero entero
# y con el toda la tabla de rutas.

set -euo pipefail

F="/var/www/climacopos/app/Http/Controllers/Panel/HardwareController.php"

cp "$F" "${F}.bak"
echo "Copia en ${F}.bak"

python3 - "$F" <<'PYEOF'
import re, sys

ruta = sys.argv[1]
s = open(ruta, encoding='utf-8').read()

# El bloque mal colocado: desde el comentario hasta el cierre del metodo,
# todo lo que hay antes de `class HardwareController`
inicio = s.find('/**\n     * Ajustes que son del SALON')

if inicio == -1:
    inicio = s.find('* Ajustes que son del SALON')
    if inicio != -1:
        inicio = s.rfind('/**', 0, inicio)

pos_clase = s.find('class HardwareController')

if inicio == -1 or pos_clase == -1 or inicio > pos_clase:
    print('No hay nada que mover, o ya esta bien colocado.')
    sys.exit(0)

bloque = s[inicio:pos_clase].rstrip()

# Fuera de donde esta
s = s[:inicio] + s[pos_clase:]

# El metodo, ya bien indentado
metodo = """
    /**
     * Ajustes que son del SALON, no de un terminal concreto.
     *
     * Vive en la pantalla de Hardware por comodidad, pero se guarda en
     * `empresas`: si hubiera dos terminales no tendria sentido que se
     * comportaran distinto al cobrar.
     */
    public function guardarSalon(Request $peticion)
    {
        $datos = $peticion->validate([
            'tras_cobrar' => ['required', 'in:NADA,SELECTOR,INICIO'],
        ]);

        tenant()->update($datos);

        Auditoria::registrar('ajuste_salon', 'empresas', tenant('id'), $datos);

        return back()->with('exito', 'Ajuste guardado.');
    }
"""

# Antes de la ultima llave del fichero
ultima = s.rstrip().rfind('}')
s = s[:ultima] + metodo + '}\n'

open(ruta, 'w', encoding='utf-8').write(s)
print('Metodo movido dentro de la clase.')
PYEOF

echo
php -l "$F"
