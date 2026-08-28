<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\FacturaPlataforma;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Suscripciones de los salones a la plataforma.
 *
 * CICLO DE MOROSIDAD
 *
 *   1er impago  → MOROSA
 *                 Avisos en el panel. Todo sigue funcionando.
 *
 *   2º impago   → SUSPENDIDA
 *                 Solo lectura: puede consultar su agenda y sus clientes,
 *                 pero no vender, no reservar y no sacar informes.
 *
 *   +90 días    → borrado, tras avisar por email.
 *
 * La suspensión NUNCA entra en mitad de una jornada: se programa para la
 * madrugada siguiente. Bloquear un TPV con clientas esperando pierde al
 * cliente aunque pague al día siguiente.
 */
class GestorSuscripciones
{
    protected const API = 'https://api.stripe.com/v1';

    public const IMPAGOS_PARA_SUSPENDER = 2;
    public const DIAS_HASTA_BORRADO = 90;

    // ------------------------------------------------------------------
    // Alta y cambio de plan
    // ------------------------------------------------------------------

    /** Enlace de pago para contratar o cambiar de plan. */
    public function enlaceContratacion(Empresa $empresa, Plan $plan, string $ciclo, string $urlExito, string $urlCancelar): string
    {
        $precio = $ciclo === 'ANUAL' ? $plan->stripe_price_ano : $plan->stripe_price_mes;

        if (blank($precio)) {
            throw new RuntimeException(
                'El plan «' . $plan->nombre . '» no tiene precio configurado en Stripe. '
                . 'Créalo en el panel de administración.'
            );
        }

        $cliente = $this->clienteStripe($empresa);

        $datos = [
            'mode'        => 'subscription',
            'customer'    => $cliente,
            'success_url' => $urlExito,
            'cancel_url'  => $urlCancelar,
            'line_items'  => [['price' => $precio, 'quantity' => 1]],
            'metadata'    => ['empresa_id' => (string) $empresa->id, 'plan_id' => (string) $plan->id],
            'subscription_data' => [
                'metadata' => ['empresa_id' => (string) $empresa->id, 'plan_id' => (string) $plan->id],
            ],
        ];

        // Si todavía está en prueba, se respetan los días que le quedan
        if ($empresa->estado === 'PRUEBA' && $empresa->prueba_hasta?->isFuture()) {
            $datos['subscription_data']['trial_end'] = $empresa->prueba_hasta->endOfDay()->timestamp;
        }

        $respuesta = $this->peticion('post', '/checkout/sessions', $datos);

        if ($respuesta->failed()) {
            throw new RuntimeException($respuesta->json('error.message') ?? 'Error al contactar con Stripe.');
        }

        return $respuesta->json('url');
    }

    /**
     * Portal de facturación de Stripe: cambiar tarjeta, ver facturas,
     * cancelar. Lo gestiona Stripe para no guardar datos de tarjeta.
     */
    public function portalFacturacion(Empresa $empresa, string $urlVuelta): string
    {
        if (blank($empresa->stripe_customer_id)) {
            throw new RuntimeException('Este salón todavía no tiene suscripción.');
        }

        $respuesta = $this->peticion('post', '/billing_portal/sessions', [
            'customer'   => $empresa->stripe_customer_id,
            'return_url' => $urlVuelta,
        ]);

        if ($respuesta->failed()) {
            throw new RuntimeException($respuesta->json('error.message') ?? 'No se pudo abrir el portal.');
        }

        return $respuesta->json('url');
    }

    protected function clienteStripe(Empresa $empresa): string
    {
        if (filled($empresa->stripe_customer_id)) {
            return $empresa->stripe_customer_id;
        }

        $respuesta = $this->peticion('post', '/customers', [
            'email' => $empresa->email,
            'name'  => $empresa->razon_social ?: $empresa->nombre_comercial,
            'metadata' => ['empresa_id' => (string) $empresa->id, 'slug' => $empresa->slug],
        ]);

        if ($respuesta->failed()) {
            throw new RuntimeException($respuesta->json('error.message') ?? 'No se pudo crear el cliente.');
        }

        $id = $respuesta->json('id');
        $empresa->forceFill(['stripe_customer_id' => $id])->save();

        return $id;
    }

    // ------------------------------------------------------------------
    // Ciclo de vida
    // ------------------------------------------------------------------

    public function activar(Empresa $empresa, ?Plan $plan = null, ?Carbon $hasta = null): void
    {
        /**
         * Los campos se ponen a NULL a proposito: activar una cuenta
         * significa borrar todo rastro de morosidad.
         */
        $atributos = [
            'estado'                 => 'ACTIVA',
            'impagos'                => 0,
            'primer_impago_en'       => null,
            'suspension_efectiva_en' => null,
            'suspendida_en'          => null,
            'aviso_borrado_en'       => null,
            'borrar_a_partir_de'     => null,
        ];

        if ($plan) {
            $atributos['plan_id'] = $plan->id;
        }

        if ($hasta) {
            $atributos['suscripcion_hasta'] = $hasta;
        }

        $empresa->forceFill($atributos)->save();
    }

    /**
     * Registra un impago y aplica lo que toque.
     * Devuelve el estado resultante.
     */
    public function registrarImpago(Empresa $empresa): string
    {
        $impagos = (int) $empresa->impagos + 1;

        $atributos = [
            'impagos'          => $impagos,
            'primer_impago_en' => $empresa->primer_impago_en ?? now(),
        ];

        if ($impagos < self::IMPAGOS_PARA_SUSPENDER) {
            // Primer impago: solo aviso, todo sigue funcionando
            $atributos['estado'] = 'MOROSA';
        } else {
            $atributos['estado'] = 'SUSPENDIDA';
            $atributos['suspendida_en'] = now();

            /**
             * La suspensión entra la madrugada siguiente, no ahora.
             * A las 4:00 no hay ningún salón trabajando.
             */
            $atributos['suspension_efectiva_en'] = now()->addDay()->setTime(4, 0);
            $atributos['borrar_a_partir_de'] = now()->addDays(self::DIAS_HASTA_BORRADO);
        }

        $empresa->forceFill($atributos)->save();

        // Aviso al salon. El tono cambia segun sea el primero o el segundo.
        $correos = new \App\Services\Correo\CorreosPlataforma();

        $impagos < self::IMPAGOS_PARA_SUSPENDER
            ? $correos->primerImpago($empresa->fresh())
            : $correos->suspensionInminente($empresa->fresh());

        Log::info('Impago registrado', [
            'empresa' => $empresa->slug,
            'impagos' => $impagos,
            'estado'  => $atributos['estado'],
        ]);

        return $atributos['estado'];
    }

    public function cancelar(Empresa $empresa, bool $alTerminar = true): void
    {
        if (filled($empresa->stripe_subscription_id)) {
            $this->peticion(
                $alTerminar ? 'post' : 'delete',
                '/subscriptions/' . $empresa->stripe_subscription_id,
                $alTerminar ? ['cancel_at_period_end' => 'true'] : [],
            );
        }

        $empresa->forceFill($alTerminar
            ? ['cancela_al_terminar' => true]
            : ['estado' => 'CANCELADA', 'borrar_a_partir_de' => now()->addDays(self::DIAS_HASTA_BORRADO)]
        )->save();
    }

    // ------------------------------------------------------------------
    // Estado efectivo
    // ------------------------------------------------------------------

    /** ¿Puede este salón escribir (vender, reservar, editar)? */
    public static function puedeEscribir(Empresa $empresa): bool
    {
        if (! in_array($empresa->estado, ['SUSPENDIDA', 'CANCELADA'], true)) {
            return true;
        }

        // Suspendida pero todavía no ha llegado la hora
        return $empresa->suspension_efectiva_en?->isFuture() ?? false;
    }

    /** ¿Puede ver informes? Se cortan antes que la escritura. */
    public static function puedeVerInformes(Empresa $empresa): bool
    {
        return self::puedeEscribir($empresa);
    }

    public static function enSoloLectura(Empresa $empresa): bool
    {
        return ! self::puedeEscribir($empresa);
    }

    // ------------------------------------------------------------------
    // Facturas
    // ------------------------------------------------------------------

    public function registrarFactura(Empresa $empresa, array $factura): FacturaPlataforma
    {
        return FacturaPlataforma::updateOrCreate(
            ['stripe_factura_id' => $factura['id']],
            [
                'empresa_id'    => $empresa->id,
                'numero'        => $factura['number'] ?? null,
                'importe'       => ($factura['amount_due'] ?? 0) / 100,
                'impuesto'      => ($factura['tax'] ?? 0) / 100,
                'moneda'        => strtoupper($factura['currency'] ?? 'eur'),
                'estado'        => match ($factura['status'] ?? '') {
                    'paid'          => 'PAGADA',
                    'open'          => 'PENDIENTE',
                    'uncollectible' => 'IMPAGADA',
                    'void'          => 'ANULADA',
                    default         => 'BORRADOR',
                },
                'periodo_desde' => isset($factura['period_start'])
                                   ? Carbon::createFromTimestamp($factura['period_start'])->toDateString() : null,
                'periodo_hasta' => isset($factura['period_end'])
                                   ? Carbon::createFromTimestamp($factura['period_end'])->toDateString() : null,
                'pagada_en'     => ($factura['status'] ?? '') === 'paid' ? now() : null,
                'intentos_cobro'=> $factura['attempt_count'] ?? 0,
                'url_factura'   => $factura['hosted_invoice_url'] ?? null,
                'url_pago'      => $factura['hosted_invoice_url'] ?? null,
            ],
        );
    }

    // ------------------------------------------------------------------

    protected function peticion(string $metodo, string $ruta, array $datos = [])
    {
        $clave = config_plataforma('stripe_secreto') ?: config('pagos.stripe.secreto');

        if (blank($clave)) {
            throw new RuntimeException(
                'Faltan las claves de Stripe. Configúralas en Administración → Pagos.'
            );
        }

        $peticion = Http::asForm()->withToken($clave)->timeout(20)
            ->withHeaders(['Stripe-Version' => '2024-06-20']);

        return match ($metodo) {
            'get'    => $peticion->get(self::API . $ruta, $datos),
            'delete' => $peticion->delete(self::API . $ruta),
            default  => $peticion->post(self::API . $ruta, $this->aplanar($datos)),
        };
    }

    protected function aplanar(array $datos, string $prefijo = ''): array
    {
        $salida = [];

        foreach ($datos as $clave => $valor) {
            if ($valor === null) {
                continue;
            }

            $nombre = $prefijo === '' ? (string) $clave : $prefijo . '[' . $clave . ']';

            if (is_array($valor)) {
                $salida += $this->aplanar($valor, $nombre);
            } elseif (is_bool($valor)) {
                $salida[$nombre] = $valor ? 'true' : 'false';
            } else {
                $salida[$nombre] = $valor;
            }
        }

        return $salida;
    }
}
