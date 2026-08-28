<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PagoOnline;
use App\Models\Reserva;
use App\Services\Pagos\GestorPagos;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct(
        protected GestorPagos $pagos = new GestorPagos(),
    ) {
    }

    /**
     * Pantalla intermedia antes de ir a la pasarela.
     *
     * Se muestra un resumen y un botón, en lugar de redirigir a Stripe de
     * golpe: saltar de repente a una pantalla de tarjeta que el cliente no
     * esperaba es la mejor forma de que abandone la reserva.
     */
    public function mostrar(string $codigo)
    {
        $reserva = Reserva::with('lineas.articulo')->where('codigo', $codigo)->firstOrFail();

        $pago = $reserva->pagos()->where('estado', 'INICIADO')->latest('id')->first();

        if ($reserva->importe_pagado > 0) {
            return redirect()->route('portal.reserva', $codigo);
        }

        return view('portal.pago', [
            'reserva' => $reserva,
            'pago'    => $pago,
            'empresa' => tenant(),
            'servicio' => $reserva->lineas->first()?->articulo,
        ]);
    }

    /** Crea la sesión y manda al cliente a la pasarela. */
    public function iniciar(Request $peticion, string $codigo)
    {
        $reserva = Reserva::with('lineas.articulo')->where('codigo', $codigo)->firstOrFail();
        $servicio = $reserva->lineas->first()?->articulo;

        if (! $servicio || $servicio->politica_pago === 'NINGUNO') {
            return redirect()->route('portal.reserva', $codigo);
        }

        try {
            $pago = $this->pagos->paraReserva(
                $reserva,
                $servicio,
                route('portal.pago.vuelta', ['codigo' => $codigo]),
                route('portal.pago', ['codigo' => $codigo]) . '?cancelado=1',
            );
        } catch (\RuntimeException $e) {
            return back()->with('error',
                'No se pudo iniciar el pago. Llama al salón y lo arreglamos: ' . $e->getMessage());
        }

        return redirect()->away($pago->url_pago);
    }

    /**
     * Vuelta desde la pasarela.
     *
     * No se da el pago por bueno solo porque el cliente vuelva por aquí:
     * la URL de retorno se puede escribir a mano. Se consulta el estado
     * real en la pasarela; el webhook es el que manda, esto es solo para
     * que el cliente vea el resultado al momento.
     */
    public function vuelta(string $codigo)
    {
        $reserva = Reserva::where('codigo', $codigo)->firstOrFail();

        $pago = $reserva->pagos()->latest('id')->first();

        if ($pago && $pago->estado === 'INICIADO') {
            $estado = $this->pagos->pasarela()->consultar($pago);

            if (($estado['estado'] ?? null) === 'PAGADO') {
                $this->pagos->confirmar($pago, $estado['cargo_id'] ?? null);
            }
        }

        return redirect()->route('portal.reserva', $codigo)
            ->with($pago?->fresh()->estaPagado() ? 'exito' : 'error',
                   $pago?->fresh()->estaPagado()
                       ? '¡Pago recibido! Tu cita está reservada.'
                       : 'Todavía no nos consta el pago. Si lo has hecho, se confirmará en unos segundos.');
    }
}
