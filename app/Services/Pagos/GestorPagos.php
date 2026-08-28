<?php

namespace App\Services\Pagos;

use App\Models\Articulo;
use App\Models\Auditoria;
use App\Models\PagoOnline;
use App\Models\Reserva;
use Illuminate\Support\Str;
use RuntimeException;

class GestorPagos
{
    public function __construct(
        protected ?Pasarela $pasarela = null,
    ) {
        $this->pasarela ??= $this->resolverPasarela();
    }

    protected function resolverPasarela(): Pasarela
    {
        return match (config('pagos.pasarela', 'stripe')) {
            'redsys' => throw new RuntimeException('Redsys todavía no está implementado.'),
            default  => new PasarelaStripe(),
        };
    }

    public function pasarela(): Pasarela
    {
        return $this->pasarela;
    }

    /** ¿Puede este salón cobrar online ahora mismo? */
    public function disponible(): bool
    {
        $empresa = tenant();

        return $empresa
            && filled($empresa->stripe_connect_id)
            && $empresa->stripe_cobros_activos;
    }

    /**
     * Crea el pago pendiente de una reserva y devuelve la URL de la pasarela.
     */
    public function paraReserva(Reserva $reserva, Articulo $servicio, string $urlExito, string $urlCancelar): PagoOnline
    {
        if (! $this->disponible()) {
            throw new RuntimeException('Este salón no tiene los pagos online activados.');
        }

        $importe = $servicio->importeFianza();

        if ($importe <= 0) {
            throw new RuntimeException('Este servicio no requiere pago por adelantado.');
        }

        $pago = PagoOnline::create([
            'reserva_id'          => $reserva->id,
            'cliente_id'          => $reserva->cliente_id,
            'pasarela'            => 'STRIPE',
            'tipo'                => $servicio->politica_pago === 'TOTAL' ? 'TOTAL' : 'FIANZA',
            'importe'             => $importe,
            'comision_plataforma' => $this->comision($importe),
            'referencia'          => 'PG-' . strtoupper(Str::random(10)),
            'estado'              => 'INICIADO',
        ]);

        $url = $this->pasarela->iniciar($pago, $urlExito, $urlCancelar);

        $reserva->update([
            'pago_tipo'     => $pago->tipo,
            'importe_total' => max((float) $reserva->importe_total, (float) $servicio->precio),
        ]);

        Auditoria::registrar('pago_iniciado', 'pagos_online', $pago->id, [
            'referencia' => $pago->referencia,
            'importe'    => (float) $importe,
            'reserva'    => $reserva->codigo,
        ]);

        return $pago->fresh();
    }

    /** Marca el pago como cobrado y lo refleja en la reserva. */
    public function confirmar(PagoOnline $pago, ?string $cargoId = null): void
    {
        if ($pago->estado === 'PAGADO') {
            return;   // el webhook puede llegar dos veces
        }

        $pago->update([
            'estado'    => 'PAGADO',
            'cargo_id'  => $cargoId ?? $pago->cargo_id,
            'pagado_en' => now(),
        ]);

        if ($reserva = $pago->reserva) {
            $reserva->update([
                'importe_pagado' => (float) $reserva->importe_pagado + (float) $pago->importe,
            ]);

            // Con confirmación automática, pagar equivale a confirmar
            if ($reserva->estado === 'PENDIENTE' && config_empresa('confirmar_al_pagar', true)) {
                $reserva->confirmar();
                \App\Models\Aviso::resolverDeReserva($reserva->id);
            }
        }

        // Recibo al cliente
        (new \App\Services\Correo\GestorCorreos())->pagoRecibido($pago->fresh());

        Auditoria::registrar('pago_cobrado', 'pagos_online', $pago->id, [
            'referencia' => $pago->referencia,
            'importe'    => (float) $pago->importe,
        ]);
    }

    /**
     * Devolución. Se llama al rechazar una reserva o al cancelarla
     * dentro de plazo.
     */
    public function devolver(Reserva $reserva, ?string $motivo = null): int
    {
        $devueltos = 0;

        foreach ($reserva->pagos()->where('estado', 'PAGADO')->get() as $pago) {
            if ($this->pasarela->devolver($pago, null, $motivo)) {
                $devueltos++;

                (new \App\Services\Correo\GestorCorreos())->devolucionHecha($pago->fresh());

                Auditoria::registrar('pago_devuelto', 'pagos_online', $pago->id, [
                    'referencia' => $pago->referencia,
                    'importe'    => (float) $pago->importe,
                    'motivo'     => $motivo,
                ]);
            }
        }

        if ($devueltos > 0) {
            $reserva->update(['importe_pagado' => 0]);
        }

        return $devueltos;
    }

    /** Sincroniza con la pasarela los pagos que quedaron a medias. */
    public function sincronizarPendientes(): int
    {
        $actualizados = 0;

        $pendientes = PagoOnline::where('estado', 'INICIADO')
            ->where('created_at', '>', now()->subDays(2))
            ->get();

        foreach ($pendientes as $pago) {
            $estado = $this->pasarela->consultar($pago);

            if (($estado['estado'] ?? null) === 'PAGADO') {
                $this->confirmar($pago, $estado['cargo_id'] ?? null);
                $actualizados++;
            } elseif (($estado['estado'] ?? null) === 'CADUCADO') {
                $pago->update(['estado' => 'CADUCADO']);
                $actualizados++;
            }
        }

        return $actualizados;
    }

    protected function comision(float $importe): float
    {
        $pct = (float) (tenant()->comision_plataforma_pct ?? 0);

        return $pct > 0 ? round($importe * ($pct / 100), 2) : 0.0;
    }
}
