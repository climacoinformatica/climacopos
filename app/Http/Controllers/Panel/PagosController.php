<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\PagoOnline;
use App\Services\Pagos\GestorPagos;
use App\Services\Pagos\PasarelaStripe;
use Illuminate\Http\Request;

class PagosController extends Controller
{
    public function index()
    {
        $empresa = tenant();

        return view('panel.ajustes.pagos', [
            'empresa'   => $empresa,
            'activo'    => (new GestorPagos())->disponible(),
            'pagos'     => PagoOnline::with('reserva')->orderByDesc('id')->limit(25)->get(),
            'totales'   => [
                'cobrado'  => (float) PagoOnline::pagados()->sum('importe'),
                'devuelto' => (float) PagoOnline::sum('devuelto_importe'),
                'pendiente'=> (float) PagoOnline::where('estado', 'INICIADO')->sum('importe'),
            ],
            'configurado' => filled(config('pagos.stripe.secreto')),
        ]);
    }

    /**
     * Crea la cuenta conectada y manda al salón a completarla en Stripe.
     *
     * El alta se hace en Stripe, no aquí: piden documentación, titularidad
     * e IBAN, y esos datos no deben pasar por nosotros.
     */
    public function conectar()
    {
        $empresa = tenant();
        $pasarela = new PasarelaStripe();

        try {
            if (blank($empresa->stripe_connect_id)) {
                $cuentaId = $pasarela->crearCuenta();

                $empresa->forceFill([
                    'stripe_connect_id'     => $cuentaId,
                    'stripe_connect_estado' => 'PENDIENTE',
                ])->save();

                Auditoria::registrar('stripe_cuenta_creada', 'empresas', $empresa->id,
                    ['cuenta' => $cuentaId]);
            }

            $url = $pasarela->enlaceAlta(
                $empresa->stripe_connect_id,
                route('panel.ajustes.pagos'),
                route('panel.ajustes.pagos.conectar'),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Stripe: ' . $e->getMessage());
        }

        return redirect()->away($url);
    }

    /** Vuelve a preguntar a Stripe en qué estado está la cuenta. */
    public function comprobar()
    {
        $empresa = tenant();

        if (blank($empresa->stripe_connect_id)) {
            return back()->with('error', 'Todavía no hay ninguna cuenta conectada.');
        }

        $estado = (new PasarelaStripe())->estadoCuenta($empresa->stripe_connect_id);

        $empresa->forceFill([
            'stripe_connect_estado' => $estado['estado'],
            'stripe_cobros_activos' => $estado['cobros'],
            'stripe_verificado_en'  => $estado['cobros'] ? now() : null,
        ])->save();

        $mensaje = match ($estado['estado']) {
            'ACTIVA'      => 'Cuenta verificada. Ya puedes cobrar reservas online.',
            'PENDIENTE'   => 'Stripe todavía está revisando tus datos. Vuelve a comprobarlo en un rato.',
            'RESTRINGIDA' => 'Stripe pide más documentación. Entra en tu cuenta para completarla.',
            default       => 'No hay cuenta conectada.',
        };

        return back()->with($estado['cobros'] ? 'exito' : 'error', $mensaje);
    }

    /** Devolución manual desde el panel. */
    public function devolver(Request $peticion, PagoOnline $pago)
    {
        $datos = $peticion->validate([
            'importe' => ['nullable', 'numeric', 'min:0.01'],
            'motivo'  => ['nullable', 'string', 'max:255'],
        ]);

        if (! $pago->esDevolvible()) {
            return back()->with('error', 'Este pago no admite devolución.');
        }

        $ok = (new GestorPagos())->pasarela()->devolver(
            $pago,
            isset($datos['importe']) ? (float) $datos['importe'] : null,
            $datos['motivo'] ?? 'Devolución desde el panel',
        );

        return back()->with(
            $ok ? 'exito' : 'error',
            $ok ? 'Devolución realizada. El cliente la verá en su banco en unos días.'
                : 'No se pudo devolver: ' . ($pago->fresh()->error ?? 'error de la pasarela'),
        );
    }

    public function sincronizar()
    {
        $cuantos = (new GestorPagos())->sincronizarPendientes();

        return back()->with('exito', "{$cuantos} pago(s) actualizado(s).");
    }
}
