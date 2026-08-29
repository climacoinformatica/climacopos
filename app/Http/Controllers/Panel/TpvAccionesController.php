<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\CierreJornada;
use App\Models\TicketCobro;
use App\Services\GestorCierre;
use App\Services\GestorImpresion;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

/**
 * Acciones de caja accesibles desde el propio TPV.
 *
 * Son las tres cosas que en el mostrador se piden a diario y que hasta
 * ahora obligaban a salir del punto de venta: abrir el cajon, leer la X
 * y sacar la Z.
 *
 * OJO CON LA Z
 *
 * Aqui NO se cierra la jornada. El cierre real necesita contar el
 * efectivo y sigue viviendo en la pantalla de Caja, detras del permiso
 * caja.cierre y de la reautenticacion con contrasena. Lo que hace este
 * controlador es enseñar el ultimo cierre y volver a sacarlo por
 * impresora, que es el 90% de las veces que alguien pulsa «Z».
 */
class TpvAccionesController extends Controller
{
    public function __construct(
        protected GestorCierre $cierre = new GestorCierre(),
        protected GestorImpresion $impresion = new GestorImpresion(),
    ) {
    }

    // ------------------------------------------------------------------
    // Cajon portamonedas
    // ------------------------------------------------------------------

    /**
     * Abre el cajon sin que haya venta.
     *
     * Queda en la auditoria a proposito: un cajon que se abre solo,
     * fuera de un cobro, es justo lo que hay que poder repasar luego si
     * el arqueo no cuadra.
     */
    public function abrirCajon(Request $peticion)
    {
        $terminal = SesionSalon::terminal();

        if (! $terminal) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Este equipo no esta vinculado a ningun terminal.',
            ], 422);
        }

        if ($terminal->ajuste('cajon_modo', 'IMPRESORA') === 'NINGUNO') {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Este terminal esta configurado sin cajon portamonedas.',
            ], 422);
        }

        try {
            $trabajo = $this->impresion->abrirCajon($terminal);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se ha podido enviar la orden al cajon.',
            ], 500);
        }

        Auditoria::registrar('cajon_abierto', 'terminales', $terminal->id, [
            'motivo'  => $peticion->input('motivo'),
            'trabajo' => $trabajo?->id,
        ], SesionSalon::usuario()?->id);

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Cajon abierto.',
        ]);
    }

    // ------------------------------------------------------------------
    // Informe X
    // ------------------------------------------------------------------

    /** Datos de la jornada en curso, para verlos en pantalla. */
    public function verX()
    {
        return response()->json([
            'ok'      => true,
            'informe' => $this->datosX(),
        ]);
    }

    public function imprimirX()
    {
        try {
            $trabajo = $this->impresion->informeX($this->cierre->resumen());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo encolar el informe X', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se ha podido enviar a la impresora. Comprueba que '
                           . 'el conector este funcionando en este equipo.',
            ], 500);
        }

        if (! $trabajo) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Este equipo no esta vinculado a ningun terminal.',
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Informe X enviado a la impresora.',
        ]);
    }

    // ------------------------------------------------------------------
    // Informe Z
    // ------------------------------------------------------------------

    /**
     * Estado del ultimo cierre y de lo que queda por cerrar.
     *
     * Se devuelven las dos cosas juntas porque quien pulsa «Z» puede
     * querer dos cosas distintas: repetir el papel del cierre de ayer, o
     * cerrar hoy. Enseñar solo una de las dos lleva a equivocarse.
     */
    public function verZ()
    {
        $ultimo = CierreJornada::with('usuario')->orderByDesc('fecha_fin')->first();
        $resumen = $this->cierre->resumen();

        return response()->json([
            'ok'     => true,
            'ultimo' => $ultimo ? [
                'id'          => $ultimo->id,
                'fecha'       => $ultimo->fecha_fin->format('d/m/Y H:i'),
                'usuario'     => $ultimo->usuario?->nombre,
                'tickets'     => (int) $ultimo->num_tickets,
                'ventas'      => (float) $ultimo->total_ventas,
                'contado'     => (float) $ultimo->efectivo_contado,
                'descuadre'   => (float) $ultimo->descuadre,
                'hay_ajuste'  => $ultimo->hayDescuadre(),
                'url_detalle' => route('panel.caja.cierre', $ultimo),
            ] : null,
            'pendiente' => [
                'desde'            => $resumen['desde']->format('d/m/Y H:i'),
                'tickets'          => (int) $resumen['num_tickets'],
                'ventas'           => (float) $resumen['total_ventas'],
                'efectivo_teorico' => (float) $resumen['efectivo_teorico'],
                'hay_movimientos'  => $resumen['num_tickets'] > 0
                                      || $resumen['movimientos']->isNotEmpty(),
            ],

            // La pantalla donde SI se puede cerrar de verdad
            'url_caja'    => route('panel.caja'),
            'puede_cerrar'=> (bool) SesionSalon::usuario()
                                ?->tienePermiso(\App\Support\Permisos::CAJA_CIERRE),
        ]);
    }

    /** Vuelve a sacar por impresora el ultimo cierre ya hecho. */
    public function imprimirZ(CierreJornada $cierre)
    {
        try {
            $trabajo = $this->impresion->cierre($cierre);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo encolar el informe Z', [
                'cierre' => $cierre->id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se ha podido enviar a la impresora. Comprueba que '
                           . 'el conector este funcionando en este equipo.',
            ], 500);
        }

        if (! $trabajo) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Este equipo no esta vinculado a ningun terminal.',
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Informe Z enviado a la impresora.',
        ]);
    }

    // ------------------------------------------------------------------

    /** Lo mismo que sale en papel, pero para pintarlo en pantalla. */
    protected function datosX(): array
    {
        $resumen = $this->cierre->resumen();

        $medios = [];

        foreach ($resumen['por_medio'] as $medio => $importe) {
            $medios[] = [
                'nombre'  => TicketCobro::MEDIOS[$medio] ?? $medio,
                'importe' => (float) $importe,
            ];
        }

        return [
            'desde'            => $resumen['desde']->format('d/m/Y H:i'),
            'emitido'          => now()->format('d/m/Y H:i'),
            'tickets'          => (int) $resumen['num_tickets'],
            'base'             => (float) $resumen['total_base'],
            'impuesto'         => (float) $resumen['total_impuesto'],
            'etiqueta_impuesto'=> tenant('regimen_fiscal') === 'IVA' ? 'IVA' : 'IGIC',
            'ventas'           => (float) $resumen['total_ventas'],
            'ticket_medio'     => (float) $resumen['ticket_medio'],
            'medios'           => $medios,
            'efectivo_inicial' => (float) $resumen['efectivo_inicial'],
            'efectivo_ventas'  => (float) ($resumen['por_medio']['EFECTIVO'] ?? 0),
            'entradas'         => (float) $resumen['entradas'],
            'salidas'          => (float) $resumen['salidas'],
            'efectivo_teorico' => (float) $resumen['efectivo_teorico'],
            'por_profesional'  => collect($resumen['por_profesional'])
                                    ->map(fn ($importe, $nombre) => [
                                        'nombre'  => $nombre,
                                        'importe' => (float) $importe,
                                    ])->values(),
            'formacion'        => (int) $resumen['formacion'],
        ];
    }
}
