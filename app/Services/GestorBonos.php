<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Auditoria;
use App\Models\Bono;
use App\Models\BonoMovimiento;
use App\Models\BonoPlantilla;
use App\Models\Cliente;
use App\Models\MonederoMovimiento;
use App\Models\Ticket;
use App\Models\TicketLinea;
use App\Models\Usuario;
use App\Models\Vale;
use App\Support\SesionSalon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bonos, monedero y vales.
 *
 * Los tres resuelven lo mismo desde ángulos distintos: dinero que la
 * clienta ya ha entregado y que se consume después.
 *
 *   BONO      atado a servicios concretos. «5 manicuras».
 *   MONEDERO  saldo libre a nombre del cliente.
 *   VALE      importe al portador, con código. Puede regalarlo.
 */
class GestorBonos
{
    // ------------------------------------------------------------------
    // Venta de bonos
    // ------------------------------------------------------------------

    /**
     * Emite un bono para un cliente.
     *
     * Se llama al cobrar un ticket que lleve un artículo de tipo bono.
     */
    public function vender(BonoPlantilla $plantilla, Cliente $cliente, ?Ticket $ticket = null): Bono
    {
        return DB::transaction(function () use ($plantilla, $cliente, $ticket) {
            $bono = Bono::create([
                'plantilla_id'     => $plantilla->id,
                'cliente_id'       => $cliente->id,
                'ticket_compra_id' => $ticket?->id,
                'modalidad'        => $plantilla->modalidad,

                'sesiones_totales' => $plantilla->esDeSesiones() ? (int) $plantilla->num_sesiones : 0,
                'sesiones_usadas'  => 0,

                'saldo_inicial'    => $plantilla->esDeSesiones() ? 0 : (float) $plantilla->saldo_otorgado,
                'saldo_actual'     => $plantilla->esDeSesiones() ? 0 : (float) $plantilla->saldo_otorgado,

                'precio_pagado'    => (float) $plantilla->precio,
                'comprado_el'      => now()->toDateString(),

                /**
                 * Caducidad.
                 *
                 * Se calcula al vender, no al usar. Si se calculara sobre
                 * la marcha, cambiar la plantilla alteraría la caducidad
                 * de bonos ya vendidos, que es un cambio de condiciones a
                 * posteriori difícil de defender ante una clienta.
                 */
                'caduca_el'        => $plantilla->caducidad_meses
                                      ? now()->addMonths((int) $plantilla->caducidad_meses)->toDateString()
                                      : null,

                'estado'           => 'ACTIVO',
            ]);

            BonoMovimiento::create([
                'bono_id'    => $bono->id,
                'tipo'       => 'COMPRA',
                'sesiones'   => $bono->sesiones_totales,
                'importe'    => (float) $bono->saldo_inicial,
                'ticket_id'  => $ticket?->id,
                'usuario_id' => SesionSalon::usuario()?->id,
                'concepto'   => $plantilla->nombre,
                'fecha'      => now(),
            ]);

            Auditoria::registrar('bono_vendido', 'bonos', $bono->id, [
                'codigo'  => $bono->codigo,
                'cliente' => $cliente->nombreCompleto(),
                'precio'  => (float) $plantilla->precio,
            ]);

            return $bono->fresh();
        });
    }

    /** Bonos que este cliente puede usar para este artículo. */
    public function bonosPara(Cliente $cliente, Articulo $articulo)
    {
        return Bono::utilizables()
            ->where('cliente_id', $cliente->id)
            ->with('plantilla')
            ->get()
            ->filter(fn (Bono $bono) => $bono->cubre($articulo))
            ->values();
    }

    // ------------------------------------------------------------------
    // Consumo
    // ------------------------------------------------------------------

    /**
     * Consume una línea del ticket contra un bono.
     *
     * La línea queda a cero y se marca de qué bono salió. Así el ticket
     * refleja lo que se ha hecho, aunque no se cobre nada en el momento.
     */
    public function consumir(Bono $bono, TicketLinea $linea, ?Usuario $usuario = null): void
    {
        $usuario ??= SesionSalon::usuario();

        if (! $bono->estaDisponible()) {
            throw new RuntimeException($this->porQueNoSePuede($bono));
        }

        $articulo = $linea->articulo;

        if ($articulo && ! $bono->plantilla->cubre($articulo)) {
            throw new RuntimeException(
                'El bono «' . $bono->plantilla->nombre . '» no cubre «' . $linea->descripcion . '».'
            );
        }

        DB::transaction(function () use ($bono, $linea, $usuario) {
            $valorLinea = (float) $linea->importe;

            if ($bono->modalidad === 'SESIONES') {
                $sesiones = max(1, (int) round((float) $linea->cantidad));

                if ($sesiones > $bono->sesionesRestantes()) {
                    throw new RuntimeException(
                        'Al bono le quedan ' . $bono->sesionesRestantes()
                        . ' sesión(es) y se necesitan ' . $sesiones . '.'
                    );
                }

                $bono->increment('sesiones_usadas', $sesiones);

                $movimiento = ['sesiones' => -$sesiones, 'importe' => -$valorLinea];
            } else {
                if ($valorLinea > (float) $bono->saldo_actual + 0.001) {
                    throw new RuntimeException(
                        'El bono tiene ' . number_format((float) $bono->saldo_actual, 2, ',', '.')
                        . ' € y la línea son ' . number_format($valorLinea, 2, ',', '.') . ' €.'
                    );
                }

                $bono->decrement('saldo_actual', $valorLinea);

                $movimiento = ['sesiones' => 0, 'importe' => -$valorLinea];
            }

            BonoMovimiento::create(array_merge($movimiento, [
                'bono_id'    => $bono->id,
                'tipo'       => 'CONSUMO',
                'ticket_id'  => $linea->ticket_id,
                'usuario_id' => $usuario?->id,
                'concepto'   => $linea->descripcion,
                'fecha'      => now(),
            ]));

            // La línea pasa a cero: ya estaba pagada al comprar el bono
            $linea->forceFill([
                'bono_id'  => $bono->id,
                'dto_pct'  => 100,
            ])->save();

            $linea->calcular()->save();

            $linea->ticket->unsetRelation('lineas');
            $linea->ticket->recalcular();

            $this->marcarSiAgotado($bono->fresh());
        });
    }

    protected function marcarSiAgotado(Bono $bono): void
    {
        $agotado = $bono->modalidad === 'SESIONES'
            ? $bono->sesionesRestantes() <= 0
            : (float) $bono->saldo_actual <= 0.001;

        if ($agotado) {
            $bono->update(['estado' => 'AGOTADO']);
        }
    }

    /**
     * Mensaje concreto de por qué no se puede usar.
     * «Bono no disponible» obliga a quien atiende a adivinar.
     */
    protected function porQueNoSePuede(Bono $bono): string
    {
        return match (true) {
            $bono->estado === 'ANULADO'  => 'Este bono está anulado.',
            $bono->haCaducado()          => 'El bono caducó el ' . $bono->caduca_el->format('d/m/Y') . '.',
            $bono->estado === 'AGOTADO'  => 'Este bono ya está agotado.',
            default                      => 'Este bono no se puede usar.',
        };
    }

    // ------------------------------------------------------------------
    // Monedero
    // ------------------------------------------------------------------

    public function recargarMonedero(
        Cliente $cliente,
        float $importe,
        string $tipo = 'RECARGA',
        ?string $concepto = null,
        ?Ticket $ticket = null,
    ): MonederoMovimiento {
        if ($importe <= 0) {
            throw new RuntimeException('El importe debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($cliente, $importe, $tipo, $concepto, $ticket) {
            $cliente->increment('saldo_monedero', $importe);

            $movimiento = MonederoMovimiento::create([
                'cliente_id'    => $cliente->id,
                'tipo'          => $tipo,
                'importe'       => $importe,
                'saldo_despues' => (float) $cliente->fresh()->saldo_monedero,
                'ticket_id'     => $ticket?->id,
                'usuario_id'    => SesionSalon::usuario()?->id,
                'concepto'      => $concepto,
                'fecha'         => now(),
            ]);

            Auditoria::registrar('monedero_recarga', 'clientes', $cliente->id, [
                'importe' => $importe,
                'tipo'    => $tipo,
            ]);

            return $movimiento;
        });
    }

    public function gastarMonedero(Cliente $cliente, float $importe, ?Ticket $ticket = null): MonederoMovimiento
    {
        $importe = abs($importe);
        $saldo = (float) $cliente->saldo_monedero;

        if ($importe > $saldo + 0.001) {
            throw new RuntimeException(
                'El monedero tiene ' . number_format($saldo, 2, ',', '.')
                . ' € y se intentan usar ' . number_format($importe, 2, ',', '.') . ' €.'
            );
        }

        return DB::transaction(function () use ($cliente, $importe, $ticket) {
            $cliente->decrement('saldo_monedero', $importe);

            return MonederoMovimiento::create([
                'cliente_id'    => $cliente->id,
                'tipo'          => 'GASTO',
                'importe'       => -$importe,
                'saldo_despues' => (float) $cliente->fresh()->saldo_monedero,
                'ticket_id'     => $ticket?->id,
                'usuario_id'    => SesionSalon::usuario()?->id,
                'concepto'      => $ticket?->referencia(),
                'fecha'         => now(),
            ]);
        });
    }

    // ------------------------------------------------------------------
    // Vales
    // ------------------------------------------------------------------

    public function emitirVale(
        float $importe,
        string $origen = 'MANUAL',
        ?Cliente $cliente = null,
        ?Ticket $ticket = null,
        ?int $mesesValidez = 12,
        ?string $concepto = null,
    ): Vale {
        if ($importe <= 0) {
            throw new RuntimeException('El importe del vale debe ser mayor que cero.');
        }

        $vale = Vale::create([
            'origen'           => $origen,
            'importe_inicial'  => round($importe, 2),
            'importe_restante' => round($importe, 2),
            'cliente_id'       => $cliente?->id,
            'ticket_origen_id' => $ticket?->id,
            'emitido_el'       => now()->toDateString(),
            'caduca_el'        => $mesesValidez ? now()->addMonths($mesesValidez)->toDateString() : null,
            'concepto'         => $concepto,
        ]);

        Auditoria::registrar('vale_emitido', 'vales', $vale->id, [
            'codigo'  => $vale->codigo,
            'importe' => (float) $importe,
            'origen'  => $origen,
        ]);

        return $vale;
    }

    /**
     * Canjea un vale, total o parcialmente.
     * Devuelve cuánto se ha podido aplicar.
     */
    public function canjearVale(Vale $vale, float $importe, ?Ticket $ticket = null): float
    {
        if (! $vale->estaDisponible()) {
            throw new RuntimeException(
                $vale->haCaducado()
                    ? 'El vale caducó el ' . $vale->caduca_el->format('d/m/Y') . '.'
                    : 'Este vale ya no tiene saldo.'
            );
        }

        /**
         * Si el vale vale más que el ticket, se aplica solo lo necesario
         * y el resto se conserva. Devolver la diferencia en efectivo
         * convertiría un vale en dinero, que no es lo que se vendió.
         */
        $aplicado = round(min(abs($importe), (float) $vale->importe_restante), 2);

        $restante = round((float) $vale->importe_restante - $aplicado, 2);

        $vale->update([
            'importe_restante' => $restante,
            'estado'           => $restante <= 0.001 ? 'CANJEADO' : 'ACTIVO',
        ]);

        Auditoria::registrar('vale_canjeado', 'vales', $vale->id, [
            'codigo'  => $vale->codigo,
            'importe' => $aplicado,
            'ticket'  => $ticket?->referencia(),
        ]);

        return $aplicado;
    }

    public function buscarVale(string $codigo): ?Vale
    {
        return Vale::where('codigo', strtoupper(trim($codigo)))->first();
    }

    // ------------------------------------------------------------------
    // Mantenimiento
    // ------------------------------------------------------------------

    /** Marca como caducados los bonos y vales que hayan vencido. */
    public function caducarVencidos(): array
    {
        $bonos = Bono::where('estado', 'ACTIVO')
            ->whereNotNull('caduca_el')
            ->where('caduca_el', '<', now()->toDateString())
            ->get();

        foreach ($bonos as $bono) {
            $bono->update(['estado' => 'CADUCADO']);

            BonoMovimiento::create([
                'bono_id'  => $bono->id,
                'tipo'     => 'CADUCIDAD',
                'sesiones' => -$bono->sesionesRestantes(),
                'importe'  => -(float) $bono->saldo_actual,
                'concepto' => 'Caducado el ' . $bono->caduca_el->format('d/m/Y'),
                'fecha'    => now(),
            ]);
        }

        $vales = Vale::where('estado', 'ACTIVO')
            ->whereNotNull('caduca_el')
            ->where('caduca_el', '<', now()->toDateString())
            ->update(['estado' => 'CADUCADO']);

        return ['bonos' => $bonos->count(), 'vales' => $vales];
    }
}
