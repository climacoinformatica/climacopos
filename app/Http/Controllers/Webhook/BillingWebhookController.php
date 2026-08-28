<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Plan;
use App\Services\GestorSuscripciones;
use App\Services\Pagos\PasarelaStripe;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de las SUSCRIPCIONES de los salones a la plataforma.
 *
 * Va al dominio central (admin.climacopos.com/webhook/billing), no al de
 * cada salón: aquí se cobra la cuota, no las reservas de sus clientes.
 */
class BillingWebhookController extends Controller
{
    public function __invoke(Request $peticion)
    {
        $cuerpo = $peticion->getContent();
        $firma  = $peticion->header('Stripe-Signature', '');

        if (! (new PasarelaStripe())->verificarFirma($cuerpo, $firma)) {
            Log::warning('Webhook de facturación con firma no válida', ['ip' => $peticion->ip()]);

            return response()->json(['error' => 'firma no válida'], 400);
        }

        $evento = json_decode($cuerpo, true);
        $objeto = $evento['data']['object'] ?? [];

        match ($evento['type'] ?? '') {
            'checkout.session.completed'      => $this->altaCompletada($objeto),
            'invoice.paid'                    => $this->facturaPagada($objeto),
            'invoice.payment_failed'          => $this->facturaImpagada($objeto),
            'customer.subscription.updated'   => $this->suscripcionActualizada($objeto),
            'customer.subscription.deleted'   => $this->suscripcionCancelada($objeto),
            default                           => null,
        };

        return response()->json(['recibido' => true]);
    }

    protected function altaCompletada(array $sesion): void
    {
        if (($sesion['mode'] ?? '') !== 'subscription') {
            return;
        }

        $empresa = $this->empresaDe($sesion);

        if (! $empresa) {
            return;
        }

        $plan = isset($sesion['metadata']['plan_id'])
            ? Plan::find($sesion['metadata']['plan_id'])
            : null;

        $empresa->forceFill([
            'stripe_subscription_id' => $sesion['subscription'] ?? null,
            'stripe_customer_id'     => $sesion['customer'] ?? $empresa->stripe_customer_id,
        ])->save();

        (new GestorSuscripciones())->activar($empresa, $plan);

        Log::info('Suscripción activada', ['empresa' => $empresa->slug]);
    }

    protected function facturaPagada(array $factura): void
    {
        $empresa = $this->empresaDe($factura);

        if (! $empresa) {
            return;
        }

        $gestor = new GestorSuscripciones();
        $gestor->registrarFactura($empresa, $factura);

        // Un pago correcto borra el historial de impagos
        $gestor->activar(
            $empresa,
            null,
            isset($factura['period_end']) ? Carbon::createFromTimestamp($factura['period_end']) : null,
        );
    }

    protected function facturaImpagada(array $factura): void
    {
        $empresa = $this->empresaDe($factura);

        if (! $empresa) {
            return;
        }

        $gestor = new GestorSuscripciones();
        $gestor->registrarFactura($empresa, $factura);

        $estado = $gestor->registrarImpago($empresa);

        Log::warning('Cuota impagada', [
            'empresa' => $empresa->slug,
            'impagos' => $empresa->fresh()->impagos,
            'estado'  => $estado,
        ]);
    }

    protected function suscripcionActualizada(array $suscripcion): void
    {
        $empresa = $this->empresaDe($suscripcion);

        if (! $empresa) {
            return;
        }

        $empresa->forceFill([
            'cancela_al_terminar' => (bool) ($suscripcion['cancel_at_period_end'] ?? false),
            'suscripcion_hasta'   => isset($suscripcion['current_period_end'])
                ? Carbon::createFromTimestamp($suscripcion['current_period_end']) : null,
        ])->save();
    }

    protected function suscripcionCancelada(array $suscripcion): void
    {
        $empresa = $this->empresaDe($suscripcion);

        if (! $empresa) {
            return;
        }

        $empresa->forceFill([
            'estado'                 => 'CANCELADA',
            'suspension_efectiva_en' => now()->addDay()->setTime(4, 0),
            'borrar_a_partir_de'     => now()->addDays(GestorSuscripciones::DIAS_HASTA_BORRADO),
        ])->save();
    }

    protected function empresaDe(array $objeto): ?Empresa
    {
        if (isset($objeto['metadata']['empresa_id'])) {
            return Empresa::find($objeto['metadata']['empresa_id']);
        }

        if (isset($objeto['customer'])) {
            return Empresa::where('stripe_customer_id', $objeto['customer'])->first();
        }

        return null;
    }
}
