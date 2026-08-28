<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PagoOnline;
use App\Services\Pagos\GestorPagos;
use App\Services\Pagos\PasarelaStripe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe los avisos de Stripe.
 *
 * Es la fuente de verdad del cobro: la vuelta del navegador puede no
 * llegar nunca (el cliente cierra la pestaña, se queda sin cobertura),
 * pero el webhook sí. Todo lo que dependa del dinero se decide aquí.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $peticion)
    {
        $cuerpo = $peticion->getContent();
        $firma  = $peticion->header('Stripe-Signature', '');

        $pasarela = new PasarelaStripe();

        if (! $pasarela->verificarFirma($cuerpo, $firma)) {
            Log::warning('Webhook de Stripe con firma no válida', ['ip' => $peticion->ip()]);

            // 400 para que Stripe lo reintente si fue un problema puntual
            return response()->json(['error' => 'firma no válida'], 400);
        }

        $evento = json_decode($cuerpo, true);
        $tipo   = $evento['type'] ?? '';
        $objeto = $evento['data']['object'] ?? [];

        match ($tipo) {
            'checkout.session.completed' => $this->pagoCompletado($objeto),
            'checkout.session.expired'   => $this->pagoCaducado($objeto),
            'charge.refunded'            => $this->devolucion($objeto),
            'account.updated'            => $this->cuentaActualizada($objeto),
            default                      => null,
        };

        // Siempre 200: si respondemos error, Stripe reintenta durante días
        return response()->json(['recibido' => true]);
    }

    protected function pagoCompletado(array $sesion): void
    {
        $pago = $this->localizar($sesion);

        if (! $pago) {
            return;
        }

        (new GestorPagos())->confirmar($pago, $sesion['payment_intent'] ?? null);
    }

    protected function pagoCaducado(array $sesion): void
    {
        $pago = $this->localizar($sesion);

        if ($pago && $pago->estado === 'INICIADO') {
            $pago->update(['estado' => 'CADUCADO']);
        }
    }

    protected function devolucion(array $cargo): void
    {
        $pago = PagoOnline::where('cargo_id', $cargo['payment_intent'] ?? '')->first();

        if (! $pago) {
            return;
        }

        $devuelto = ($cargo['amount_refunded'] ?? 0) / 100;

        $pago->update([
            'devuelto_importe' => $devuelto,
            'devuelto_en'      => now(),
            'estado'           => $devuelto >= (float) $pago->importe - 0.001
                                  ? 'DEVUELTO' : 'DEVUELTO_PARCIAL',
        ]);
    }

    /** La cuenta del salón ha cambiado de estado en Stripe. */
    protected function cuentaActualizada(array $cuenta): void
    {
        $empresa = \App\Models\Empresa::where('stripe_connect_id', $cuenta['id'] ?? '')->first();

        if (! $empresa) {
            return;
        }

        $cobros = (bool) ($cuenta['charges_enabled'] ?? false);

        $empresa->forceFill([
            'stripe_cobros_activos' => $cobros,
            'stripe_connect_estado' => $cobros ? 'ACTIVA' : 'PENDIENTE',
            'stripe_verificado_en'  => $cobros ? now() : null,
        ])->save();
    }

    /**
     * Localiza el pago por la referencia que enviamos.
     *
     * OJO con el tenant: este webhook llega al dominio de la empresa, así
     * que el middleware de tenancy ya ha resuelto la conexión. Si algún
     * día se centraliza en un único endpoint, habrá que inicializar el
     * tenant desde metadata['empresa_id'] antes de consultar nada.
     */
    protected function localizar(array $sesion): ?PagoOnline
    {
        $referencia = $sesion['client_reference_id'] ?? null;
        $uuid = $sesion['metadata']['pago_uuid'] ?? null;

        return PagoOnline::when($referencia, fn ($q) => $q->where('referencia', $referencia))
            ->when(! $referencia && $uuid, fn ($q) => $q->where('uuid', $uuid))
            ->first();
    }
}
