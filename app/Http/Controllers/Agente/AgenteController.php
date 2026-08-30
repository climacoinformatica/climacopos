<?php

namespace App\Http\Controllers\Agente;

use App\Http\Controllers\Controller;
use App\Models\ColaImpresion;
use App\Models\Terminal;
use Illuminate\Http\Request;

/**
 * API que consume el Agente CLIMACO instalado en el PC del salón.
 *
 * El agente sondea cada uno o dos segundos, recoge los trabajos
 * pendientes, los envía a la impresora por socket y confirma.
 */
class AgenteController extends Controller
{
    /** El agente arranca aquí para comprobar que el token es válido. */
    public function saludo(Request $peticion)
    {
        $terminal = $peticion->attributes->get('terminal');

        return response()->json([
            'ok'       => true,
            'empresa'  => tenant('nombre_comercial'),
            'terminal' => [
                'id'     => $terminal->id,
                'nombre' => $terminal->nombre,
                'codigo' => $terminal->codigo,
            ],
            'config'   => $this->configuracion($terminal),
            'servidor' => now()->toDateTimeString(),
        ]);
    }

    /** Trabajos pendientes para este terminal. */
    public function trabajos(Request $peticion)
    {
        $terminal = $peticion->attributes->get('terminal');

        $trabajos = ColaImpresion::paraAgente($terminal->id)->limit(10)->get();

        $salida = $trabajos->map(function (ColaImpresion $trabajo) {
            $trabajo->marcarRecogido();

            return [
                'id'          => $trabajo->id,
                'tipo'        => $trabajo->tipo,
                'destino'     => $trabajo->destino,
                'descripcion' => $trabajo->descripcion,
                'payload'     => $trabajo->payload,   // base64
            ];
        });

        $terminal->forceFill(['agente_ultima_conexion' => now()])->saveQuietly();

        return response()->json([
            'trabajos' => $salida,
            'config'   => $this->configuracion($terminal),
        ]);
    }

    /**
     * El agente avisa de que ya imprimio (o de que no pudo).
     *
     * OJO CON EL SEGUNDO PARAMETRO: es un int, NO un ColaImpresion.
     *
     * Con route model binding, Laravel resuelve el modelo ANTES de que
     * InitializeTenancyByDomain haya cambiado la conexion, asi que
     * buscaba el id en la base central, no encontraba nada e inyectaba
     * un modelo vacio. Como un modelo vacio tiene terminal_id a null,
     * la comprobacion de pertenencia fallaba siempre.
     *
     * El sintoma era desconcertante: el conector RECOGIA los trabajos
     * sin problema (ahi no hay binding), los imprimia, y solo fallaba al
     * confirmarlos, con un 403 que decia «es de otro terminal» aunque en
     * la base del salon los dos ids fueran el mismo. Y como el servidor
     * reencola a los dos minutos lo que nadie confirma, el mismo informe
     * salia por la impresora una y otra vez.
     *
     * Buscandolo a mano, ya dentro del tenant, se acaba el problema. De
     * paso el filtro por terminal va en el WHERE, que es mas seguro que
     * comparar despues.
     */
    public function confirmar(Request $peticion, int $trabajo)
    {
        $terminal = $peticion->attributes->get('terminal');

        $trabajo = ColaImpresion::where('id', $trabajo)
            ->where('terminal_id', $terminal->id)
            ->first();

        abort_unless($trabajo, 404, 'Ese trabajo no existe en este terminal.');

        $datos = $peticion->validate([
            'ok'    => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        if ($datos['ok']) {
            $trabajo->marcarHecho();
        } else {
            $trabajo->marcarError($datos['error'] ?? 'Error sin detallar');

            // Un fallo de impresora merece aviso en el panel
            if ($trabajo->estado === 'ERROR') {
                \App\Models\Aviso::create([
                    'tipo'          => 'ERROR_AGENTE',
                    'referencia_id' => $trabajo->id,
                    'titulo'        => 'Fallo de impresión',
                    'mensaje'       => ($trabajo->descripcion ?: 'Trabajo') . ': ' . $trabajo->error,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Configuración del hardware de este terminal. */
    protected function configuracion(Terminal $terminal): array
    {
        $ajuste = fn (string $clave, $porDefecto = null) => $terminal->ajuste($clave, $porDefecto);

        return [
            'impresora_tickets_ip'     => $ajuste('impresora_tickets_ip'),
            'impresora_tickets_puerto' => (int) $ajuste('impresora_tickets_puerto', 9100),
            'impresora_tickets_modo'   => $ajuste('impresora_tickets_modo', 'RED'),
            'impresora_tickets_local'  => $ajuste('impresora_tickets_local'),

            'impresora_etiquetas_ip'     => $ajuste('impresora_etiquetas_ip'),
            'impresora_etiquetas_puerto' => (int) $ajuste('impresora_etiquetas_puerto', 9100),

            'cajon_modo'   => $ajuste('cajon_modo', 'IMPRESORA'),
            'cajon_puerto' => $ajuste('cajon_puerto'),

            'visor_puerto'  => $ajuste('visor_puerto'),
            'visor_baudios' => (int) $ajuste('visor_baudios', 9600),

            'intervalo_ms' => (int) $ajuste('agente_intervalo_ms', 1500),
        ];
    }
}
