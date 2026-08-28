<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketLinea;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Producción de cada profesional.
 *
 * Qué ha hecho cada uno y cuánto ha facturado, para poder pagarles.
 *
 * POR QUÉ NO SE MIRA QUIÉN COBRÓ
 *
 * Cada línea del ticket guarda quién EJECUTÓ el servicio, que no siempre
 * es quien lo cobra. Si sumáramos por el usuario del ticket, un día en
 * que alguien cobra la clienta de un compañero el reparto saldría mal,
 * y de eso depende lo que cobra cada uno a fin de mes.
 */
class GestorProduccion
{
    /**
     * Producción de un periodo.
     *
     * @return array{filas: Collection, totales: array, desde: Carbon, hasta: Carbon}
     */
    public function periodo(
        Carbon|string $desde,
        Carbon|string $hasta,
        ?int $usuarioId = null,
    ): array {
        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->endOfDay();

        /**
         * Solo tickets cobrados y no anulados.
         *
         * Los abiertos todavía pueden cambiar, y los anulados no se
         * pagan: cobrar comisión por un ticket que se anuló sería
         * pagarle dos veces al profesional si vuelve a hacerse.
         *
         * Los de formación quedan fuera por el scope global.
         */
        $lineas = TicketLinea::query()
            ->whereHas('ticket', function ($q) use ($desde, $hasta) {
                $q->where('estado', 'COBRADO')
                  ->whereBetween('fecha', [$desde, $hasta]);
            })
            ->when($usuarioId, fn ($q) => $q->where('usuario_id', $usuarioId))
            ->with(['usuario', 'articulo'])
            ->get();

        $porUsuario = $lineas->groupBy('usuario_id');

        $filas = collect();

        foreach ($porUsuario as $id => $suyas) {
            if (! $id) {
                continue;   // líneas sin ejecutor asignado
            }

            $usuario = $suyas->first()->usuario;

            if (! $usuario) {
                continue;
            }

            $servicios = $suyas->filter(fn ($l) => $l->articulo?->tipo === 'SERVICIO');
            $productos = $suyas->filter(fn ($l) => $l->articulo?->tipo === 'PRODUCTO');

            $facturadoServicios = round((float) $servicios->sum('importe'), 2);
            $facturadoProductos = round((float) $productos->sum('importe'), 2);
            $facturado = round($facturadoServicios + $facturadoProductos, 2);

            $filas->push([
                'usuario'    => $usuario,
                'servicios'  => (int) $servicios->sum('cantidad'),
                'productos'  => (int) $productos->sum('cantidad'),
                'fact_servicios' => $facturadoServicios,
                'fact_productos' => $facturadoProductos,
                'facturado'  => $facturado,

                /**
                 * Ticket medio por servicio.
                 * Dice más que el total: quien atiende a cinco clientas
                 * de 60 € produce distinto que quien atiende a quince de 20.
                 */
                'medio'      => $servicios->sum('cantidad') > 0
                                ? round($facturadoServicios / $servicios->sum('cantidad'), 2)
                                : 0.0,

                'comision'   => $this->comision($usuario, $facturado, (int) $servicios->sum('cantidad')),
            ]);
        }

        $filas = $filas->sortByDesc('facturado')->values();

        // Lo que no tiene ejecutor asignado se muestra aparte, no se reparte
        $sinAsignar = $lineas->whereNull('usuario_id');

        return [
            'filas'   => $filas,
            'desde'   => $desde,
            'hasta'   => $hasta,
            'totales' => [
                'servicios'  => (int) $filas->sum('servicios'),
                'productos'  => (int) $filas->sum('productos'),
                'facturado'  => round((float) $filas->sum('facturado'), 2),
                'comisiones' => round((float) $filas->sum('comision'), 2),

                'sin_asignar'      => $sinAsignar->count(),
                'sin_asignar_imp'  => round((float) $sinAsignar->sum('importe'), 2),
            ],
        ];
    }

    /** Producción de un solo día. Lo usa el parte de trabajo. */
    public function delDia(Carbon|string $fecha, ?int $usuarioId = null): array
    {
        return $this->periodo($fecha, $fecha, $usuarioId);
    }

    /**
     * Lo que le corresponde a un profesional.
     *
     * Tres modos, y por defecto ninguno: mientras el salón no configure
     * comisiones, esta columna sale a cero y no estorba.
     */
    protected function comision(Usuario $usuario, float $facturado, int $servicios): float
    {
        return match ($usuario->comision_tipo ?? 'NINGUNA') {
            'PORCENTAJE' => round($facturado * (float) ($usuario->comision_pct ?? 0) / 100, 2),
            'POR_SERVICIO' => round($servicios * (float) ($usuario->comision_fija ?? 0), 2),
            default => 0.0,
        };
    }

    /** Detalle de lo que hizo una persona, servicio a servicio. */
    public function detalle(int $usuarioId, Carbon|string $desde, Carbon|string $hasta)
    {
        return TicketLinea::query()
            ->where('usuario_id', $usuarioId)
            ->whereHas('ticket', function ($q) use ($desde, $hasta) {
                $q->where('estado', 'COBRADO')
                  ->whereBetween('fecha', [
                      Carbon::parse($desde)->startOfDay(),
                      Carbon::parse($hasta)->endOfDay(),
                  ]);
            })
            ->with(['ticket.cliente', 'articulo'])
            ->get()
            ->sortBy(fn ($l) => $l->ticket->fecha)
            ->values();
    }
}
