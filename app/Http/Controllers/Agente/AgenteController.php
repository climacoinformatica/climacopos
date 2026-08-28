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

    public function confirmar(Request $peticion, ColaImpresion $trabajo)
    {
        $terminal = $peticion->attributes->get('terminal');

        abort_unless($trabajo->terminal_id === $terminal->id, 403);

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
