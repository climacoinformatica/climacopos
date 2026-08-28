<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\CajaMovimiento;
use App\Models\CierreJornada;
use App\Models\Ticket;
use App\Models\TicketCobro;
use App\Models\TicketLinea;
use App\Models\Usuario;
use App\Services\GestorImpresion;
use App\Support\SesionSalon;
use Illuminate\Support\Facades\DB;

/**
 * Cierre de jornada y arqueo.
 *
 * IMPORTANTE: todas las consultas de aqui pasan por el global scope
 * ExcluirFormacion, asi que los tickets de practicas quedan fuera del
 * cierre sin que haya que acordarse de filtrarlos.
 */
class GestorCierre
{
    /** Resumen de lo pendiente de cerrar, para la pantalla previa. */
    public function resumen(): array
    {
        $ultimo = CierreJornada::ultimo();
        $desde  = $ultimo?->fecha_fin ?? now()->startOfDay()->subYear();

        $tickets = Ticket::sinCerrar()->with(['lineas.articulo.familia', 'cobros', 'lineas.usuario'])->get();

        $movimientos = CajaMovimiento::sinCerrar()->get();

        $porMedio = [];

        foreach ($tickets as $ticket) {
            foreach ($ticket->cobros as $cobro) {
                $porMedio[$cobro->medio] = round(
                    ($porMedio[$cobro->medio] ?? 0) + (float) $cobro->importe, 2
                );
            }
        }

        $porFamilia = [];
        $porProfesional = [];

        foreach ($tickets as $ticket) {
            foreach ($ticket->lineas as $linea) {
                $familia = $linea->articulo?->familia?->nombre ?? 'Sin familia';
                $porFamilia[$familia] = round(($porFamilia[$familia] ?? 0) + (float) $linea->importe, 2);

                $profesional = $linea->usuario?->nombre ?? 'Sin asignar';
                $porProfesional[$profesional] = round(
                    ($porProfesional[$profesional] ?? 0) + (float) $linea->importe, 2
                );
            }
        }

        $efectivoVentas = $porMedio['EFECTIVO'] ?? 0;
        $efectivoInicial = (float) $movimientos->where('tipo', 'APERTURA')->sum('importe');
        $entradas = (float) $movimientos->where('tipo', 'ENTRADA')->sum('importe');
        $salidas  = (float) $movimientos->where('tipo', 'SALIDA')->sum('importe');

        return [
            'desde'            => $desde,
            'tickets'          => $tickets,
            'num_tickets'      => $tickets->count(),
            'total_ventas'     => round($tickets->sum('total'), 2),
            'total_base'       => round($tickets->sum('base'), 2),
            'total_impuesto'   => round($tickets->sum('impuesto'), 2),
            'por_medio'        => $porMedio,
            'por_familia'      => $porFamilia,
            'por_profesional'  => $porProfesional,
            'movimientos'      => $movimientos,
            'efectivo_inicial' => $efectivoInicial,
            'entradas'         => $entradas,
            'salidas'          => $salidas,
            'efectivo_teorico' => round($efectivoInicial + $efectivoVentas + $entradas - $salidas, 2),
            'ticket_medio'     => $tickets->count() > 0
                                  ? round($tickets->sum('total') / $tickets->count(), 2) : 0,
            // Solo informativo: lo de formacion nunca entra en el cierre
            'formacion'        => Ticket::soloFormacion()->whereNull('cierre_id')->count(),
        ];
    }

    public function cerrar(Usuario $usuario, float $efectivoContado, ?string $observaciones = null): CierreJornada
    {
        return DB::transaction(function () use ($usuario, $efectivoContado, $observaciones) {
            $resumen = $this->resumen();

            $cierre = CierreJornada::create([
                'fecha_ini'               => $resumen['desde'],
                'fecha_fin'               => now(),
                'usuario_id'              => $usuario->id,
                'terminal_id'             => SesionSalon::terminal()?->id,
                'efectivo_inicial'        => $resumen['efectivo_inicial'],
                'efectivo_teorico'        => $resumen['efectivo_teorico'],
                'efectivo_contado'        => $efectivoContado,
                'descuadre'               => round($efectivoContado - $resumen['efectivo_teorico'], 2),
                'total_ventas'            => $resumen['total_ventas'],
                'total_base'              => $resumen['total_base'],
                'total_impuesto'          => $resumen['total_impuesto'],
                'num_tickets'             => $resumen['num_tickets'],
                'totales_por_medio'       => $resumen['por_medio'],
                'totales_por_familia'     => $resumen['por_familia'],
                'totales_por_profesional' => $resumen['por_profesional'],
                'observaciones'           => $observaciones,
            ]);

            // Se marcan los documentos incluidos.
            // El global scope garantiza que formacion NO entra.
            Ticket::sinCerrar()->update(['cierre_id' => $cierre->id]);
            CajaMovimiento::sinCerrar()->update(['cierre_id' => $cierre->id]);

            Auditoria::registrar('cierre_jornada', 'cierres_jornada', $cierre->id, [
                'tickets'   => $cierre->num_tickets,
                'ventas'    => (float) $cierre->total_ventas,
                'descuadre' => (float) $cierre->descuadre,
            ], $usuario->id);

            return $cierre;
        });
    }

    /**
     * Cierra la jornada y manda los papeles a la impresora.
     *
     * POR QUE VA APARTE DE cerrar()
     *
     * `cerrar()` esta dentro de una transaccion. Encolar la impresion ahi
     * significaria que un fallo de impresora podria deshacer el cierre
     * entero, y eso es inaceptable: el cierre ya cuadro la caja y marco
     * los tickets.
     *
     * Aqui se cierra primero, y solo despues se imprime. Si la impresora
     * falla, el cierre sigue hecho y el papel se saca cuando se arregle.
     */
    public function cerrarEImprimir(
        Usuario $usuario,
        float $efectivoContado,
        ?string $observaciones = null,
        bool $conParte = true,
    ): CierreJornada {
        $cierre = $this->cerrar($usuario, $efectivoContado, $observaciones);

        $impresion = new GestorImpresion();

        try {
            $impresion->cierre($cierre);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo imprimir el cierre', [
                'cierre' => $cierre->id,
                'error'  => $e->getMessage(),
            ]);
        }

        /**
         * El parte de trabajo va en papel APARTE.
         *
         * El cierre lo maneja quien cuadra el efectivo; el parte es
         * informacion laboral, con lo que factura cada profesional. Dos
         * papeles permiten dar uno a cada quien.
         */
        if ($conParte) {
            try {
                $impresion->parteTrabajo($cierre->fecha_fin);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('No se pudo imprimir el parte', [
                    'cierre' => $cierre->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return $cierre;
    }

    public function movimiento(string $tipo, float $importe, string $motivo, Usuario $usuario): CajaMovimiento
    {
        $movimiento = CajaMovimiento::create([
            'fecha'       => now(),
            'tipo'        => strtoupper($tipo),
            'importe'     => abs($importe),
            'motivo'      => $motivo,
            'usuario_id'  => $usuario->id,
            'terminal_id' => SesionSalon::terminal()?->id,
        ]);

        Auditoria::registrar('caja_' . strtolower($tipo), 'caja_movimientos', $movimiento->id, [
            'importe' => $importe,
            'motivo'  => $motivo,
        ], $usuario->id);

        return $movimiento;
    }
}
