<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Services\GestorCierre;
use App\Services\GestorImpresion;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cajon, informe X y cierre de jornada, desde el propio TPV.
 *
 * Son las tres cosas que se piden a diario en el mostrador y que hasta
 * ahora obligaban a salir del punto de venta.
 *
 * SOBRE LOS PERMISOS
 *
 * A proposito no se inventa ningun permiso nuevo: se reutilizan los que
 * ya existen y que ya tienen repartidos los perfiles.
 *
 *   - Cajon      -> tpv.abrir_cajon
 *   - Informe X  -> caja.entradas_salidas   (no pide contrasena)
 *   - Cerrar dia -> caja.cierre             (SI pide contrasena)
 *
 * Que el cierre pida contrasena y la X no es deliberado: leer el arqueo
 * es rutina y debe ser agil, cerrar la jornada es irreversible.
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
     * Queda en la auditoria a proposito: un cajon que se abre fuera de
     * un cobro es justo lo que hay que poder repasar si luego el arqueo
     * no cuadra.
     */
    public function abrirCajon(Request $peticion)
    {
        $terminal = SesionSalon::terminal();

        if (! $terminal) {
            return $this->fallo('Este equipo no esta vinculado a ningun terminal.', 422);
        }

        if ($terminal->ajuste('cajon_modo', 'IMPRESORA') === 'NINGUNO') {
            return $this->fallo('Este terminal esta configurado sin cajon portamonedas.', 422);
        }

        try {
            $trabajo = $this->impresion->abrirCajon($terminal);
        } catch (\Throwable $e) {
            Log::error('No se pudo abrir el cajon desde el TPV', ['error' => $e->getMessage()]);

            return $this->fallo('No se ha podido enviar la orden al cajon.', 500);
        }

        Auditoria::registrar('cajon_abierto', 'terminales', $terminal->id, [
            'origen'  => 'tpv',
            'trabajo' => $trabajo?->id,
        ], SesionSalon::usuario()?->id);

        return response()->json(['ok' => true, 'mensaje' => 'Cajón abierto.']);
    }

    // ------------------------------------------------------------------
    // Informe X
    // ------------------------------------------------------------------

    /** Como va la jornada ahora mismo, para verlo en pantalla. */
    public function verX()
    {
        return response()->json([
            'ok'      => true,
            'informe' => $this->paraPantalla($this->cierre->informeX()),
        ]);
    }

    public function imprimirX()
    {
        try {
            $trabajo = $this->impresion->informeX($this->cierre->informeX());
        } catch (\Throwable $e) {
            Log::error('No se pudo encolar el informe X', ['error' => $e->getMessage()]);

            return $this->fallo(
                'No se ha podido enviar a la impresora. Comprueba que el '
                . 'conector esté funcionando en este equipo.', 500);
        }

        if (! $trabajo) {
            return $this->fallo('Este equipo no esta vinculado a ningun terminal.', 422);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Informe X enviado a la impresora.']);
    }

    // ------------------------------------------------------------------
    // Cierre de jornada
    // ------------------------------------------------------------------

    /**
     * Lo que hay pendiente de cerrar.
     *
     * Se manda el efectivo teorico para que el modal pueda enseñar el
     * descuadre segun se teclea lo contado, antes de confirmar nada.
     */
    public function verCierre()
    {
        $resumen = $this->cierre->resumen();

        return response()->json([
            'ok'      => true,
            'informe' => $this->paraPantalla($resumen),
            'hay_algo' => $resumen['num_tickets'] > 0 || $resumen['movimientos']->isNotEmpty(),
        ]);
    }

    /**
     * Cierra la jornada de verdad.
     *
     * Misma logica que la pantalla de Caja: se apoya en
     * GestorCierre::cerrarEImprimir(), no se duplica nada. Si la
     * impresora falla, el cierre queda hecho igualmente; el papel se
     * vuelve a sacar despues desde Caja.
     */
    public function cerrar(Request $peticion)
    {
        $datos = $peticion->validate([
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
        ]);

        $resumen = $this->cierre->resumen();

        if ($resumen['num_tickets'] === 0 && $resumen['movimientos']->isEmpty()) {
            return $this->fallo('No hay nada que cerrar.', 422);
        }

        try {
            $cierre = $this->cierre->cerrarEImprimir(
                SesionSalon::usuario(),
                (float) $datos['efectivo_contado'],
                $datos['observaciones'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('Fallo el cierre desde el TPV', ['error' => $e->getMessage()]);

            return $this->fallo('No se ha podido cerrar la jornada.', 500);
        }

        return response()->json([
            'ok'       => true,
            'mensaje'  => 'Jornada cerrada.',
            'descuadre' => (float) $cierre->descuadre,
            'url'      => route('panel.caja.cierre', $cierre),
        ]);
    }

    // ------------------------------------------------------------------

    /** El mismo contenido del papel, preparado para pintarlo en pantalla. */
    protected function paraPantalla(array $datos): array
    {
        $medios = [];

        foreach ($datos['por_medio'] as $medio => $importe) {
            $medios[] = [
                'nombre'  => ucfirst(strtolower($medio)),
                'importe' => (float) $importe,
            ];
        }

        $profesionales = [];

        foreach ($datos['por_profesional'] ?? [] as $nombre => $importe) {
            $profesionales[] = ['nombre' => $nombre, 'importe' => (float) $importe];
        }

        return [
            'desde'             => $datos['desde']->format('d/m/Y H:i'),
            'emitido'           => ($datos['momento'] ?? now())->format('d/m/Y H:i'),
            'tickets'           => (int) $datos['num_tickets'],
            'base'              => (float) $datos['total_base'],
            'impuesto'          => (float) $datos['total_impuesto'],
            'etiqueta_impuesto' => tenant('regimen_fiscal') === 'IVA' ? 'IVA' : 'IGIC',
            'ventas'            => (float) $datos['total_ventas'],
            'ticket_medio'      => (float) $datos['ticket_medio'],
            'medios'            => $medios,
            'efectivo_inicial'  => (float) $datos['efectivo_inicial'],
            'efectivo_ventas'   => (float) ($datos['por_medio']['EFECTIVO'] ?? 0),
            'entradas'          => (float) $datos['entradas'],
            'salidas'           => (float) $datos['salidas'],
            'efectivo_teorico'  => (float) $datos['efectivo_teorico'],
            'por_profesional'   => $profesionales,
            'formacion'         => (int) ($datos['formacion'] ?? 0),
        ];
    }

    protected function fallo(string $mensaje, int $codigo)
    {
        return response()->json(['ok' => false, 'mensaje' => $mensaje], $codigo);
    }
}
