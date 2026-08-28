<?php

namespace App\Services\Pagos;

use App\Models\PagoOnline;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe con cuentas conectadas (Connect).
 *
 * Se habla con la API por HTTP en lugar de usar el SDK oficial: así el
 * despliegue sigue siendo copiar ficheros, sin `composer require` ni
 * dependencias que actualizar. La API de Stripe es estable y lo que
 * usamos aquí son cuatro llamadas.
 *
 * FLUJO DEL DINERO
 *
 *   Cliente final ──paga──▶ Cuenta Stripe DEL SALÓN
 *                              │
 *                              └── application_fee ──▶ Nosotros (si procede)
 *
 * El dinero nunca pasa por nuestra cuenta. Si pasara, seríamos entidad
 * de pago, que es una figura regulada por el Banco de España.
 */
class PasarelaStripe implements Pasarela
{
    protected const API = 'https://api.stripe.com/v1';

    public function __construct(
        protected ?string $clave = null,
        protected ?string $secretoWebhook = null,
    ) {
        /**
         * Las claves se leen de la configuracion de la PLATAFORMA, que se
         * edita desde admin.climacopos.com y se guarda cifrada en la base
         * central. El .env queda solo como respaldo para instalaciones
         * antiguas o entornos de desarrollo.
         */
        $this->clave ??= config_plataforma('stripe_secreto') ?: config('pagos.stripe.secreto');
        $this->secretoWebhook ??= config_plataforma('stripe_webhook') ?: config('pagos.stripe.webhook');
    }

    /** ¿Están puestas las claves de la plataforma? */
    public static function configurada(): bool
    {
        return filled(config_plataforma('stripe_secreto') ?: config('pagos.stripe.secreto'));
    }

    /**
     * Comprueba que la clave es válida preguntando el saldo.
     * Es la llamada más barata de la API y no modifica nada.
     */
    public function comprobarClave(): array
    {
        try {
            $respuesta = $this->peticion('get', '/balance');
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        if ($respuesta->failed()) {
            return [
                'ok'      => false,
                'mensaje' => $respuesta->json('error.message') ?? 'La clave no es válida.',
            ];
        }

        $modo = str_starts_with((string) $this->clave, 'sk_live') ? 'PRODUCCIÓN' : 'PRUEBAS';

        return [
            'ok'      => true,
            'modo'    => $modo,
            'mensaje' => "Conexión correcta en modo {$modo}.",
        ];
    }

    public function nombre(): string
    {
        return 'Stripe';
    }

    // ------------------------------------------------------------------
    // Cobro
    // ------------------------------------------------------------------

    public function iniciar(PagoOnline $pago, string $urlExito, string $urlCancelar): string
    {
        $empresa = tenant();

        if (blank($empresa->stripe_connect_id)) {
            throw new RuntimeException('El salón todavía no ha conectado su cuenta de Stripe.');
        }

        $reserva = $pago->reserva;

        $datos = [
            'mode'                => 'payment',
            'success_url'         => $urlExito,
            'cancel_url'          => $urlCancelar,
            'client_reference_id' => $pago->referencia,
            'expires_at'          => now()->addMinutes(30)->timestamp,

            'line_items' => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower($pago->moneda),
                    'unit_amount'  => $this->aCentimos((float) $pago->importe),
                    'product_data' => [
                        'name'        => $this->concepto($pago),
                        'description' => $reserva
                            ? $reserva->fecha->format('d/m/Y') . ' a las ' . substr($reserva->hora_ini, 0, 5)
                            : null,
                    ],
                ],
            ]],

            'metadata' => [
                'pago_uuid'   => $pago->uuid,
                'reserva'     => $reserva?->codigo,
                'empresa'     => $empresa->slug,
                'empresa_id'  => (string) $empresa->id,
            ],
        ];

        if ($pago->cliente_email ?? $reserva?->cliente_email) {
            $datos['customer_email'] = $reserva?->cliente_email;
        }

        // Comisión de la plataforma, si la hay
        $comision = $this->aCentimos((float) $pago->comision_plataforma);

        if ($comision > 0) {
            $datos['payment_intent_data']['application_fee_amount'] = $comision;
        }

        // La petición se hace EN NOMBRE de la cuenta del salón
        $respuesta = $this->peticion('post', '/checkout/sessions', $datos, $empresa->stripe_connect_id);

        if ($respuesta->failed()) {
            $mensaje = $respuesta->json('error.message') ?? 'Error al contactar con Stripe.';

            $pago->update(['estado' => 'FALLIDO', 'error' => $mensaje,
                           'respuesta' => $respuesta->body()]);

            throw new RuntimeException($mensaje);
        }

        $sesion = $respuesta->json();

        $pago->update([
            'sesion_id' => $sesion['id'],
            'url_pago'  => $sesion['url'],
            'caduca_en' => now()->addMinutes(30),
            'respuesta' => $respuesta->body(),
        ]);

        return $sesion['url'];
    }

    public function consultar(PagoOnline $pago): array
    {
        if (blank($pago->sesion_id)) {
            return ['estado' => $pago->estado];
        }

        $respuesta = $this->peticion(
            'get',
            '/checkout/sessions/' . $pago->sesion_id,
            [],
            tenant()->stripe_connect_id,
        );

        if ($respuesta->failed()) {
            return ['estado' => $pago->estado, 'error' => $respuesta->json('error.message')];
        }

        $sesion = $respuesta->json();

        return [
            'estado'      => $this->traducirEstado($sesion['payment_status'] ?? '', $sesion['status'] ?? ''),
            'cargo_id'    => $sesion['payment_intent'] ?? null,
            'importe'     => isset($sesion['amount_total']) ? $sesion['amount_total'] / 100 : null,
            'crudo'       => $sesion,
        ];
    }

    // ------------------------------------------------------------------
    // Devolución
    // ------------------------------------------------------------------

    public function devolver(PagoOnline $pago, ?float $importe = null, ?string $motivo = null): bool
    {
        if ($pago->estado !== 'PAGADO' || blank($pago->cargo_id)) {
            return false;
        }

        $importe ??= (float) $pago->importe - (float) $pago->devuelto_importe;

        if ($importe <= 0) {
            return false;
        }

        $datos = [
            'payment_intent' => $pago->cargo_id,
            'amount'         => $this->aCentimos($importe),
            'metadata'       => ['motivo' => $motivo ?? 'Cancelación'],

            /**
             * Devolver también la comisión de la plataforma.
             *
             * Si no se indica, Stripe nos deja quedarnos la comisión de un
             * pago que se ha devuelto entero. Cobrar por una reserva que
             * el salón rechazó no tiene defensa posible.
             */
            'refund_application_fee' => (float) $pago->comision_plataforma > 0,
        ];

        $respuesta = $this->peticion('post', '/refunds', $datos, tenant()->stripe_connect_id);

        if ($respuesta->failed()) {
            Log::warning('Stripe: devolución fallida', [
                'pago'  => $pago->referencia,
                'error' => $respuesta->json('error.message'),
            ]);

            $pago->update(['error' => $respuesta->json('error.message')]);

            return false;
        }

        $devolucion = $respuesta->json();
        $devueltoTotal = (float) $pago->devuelto_importe + $importe;

        $pago->update([
            'estado' => $devueltoTotal >= (float) $pago->importe - 0.001
                        ? 'DEVUELTO' : 'DEVUELTO_PARCIAL',
            'devolucion_id'     => $devolucion['id'] ?? null,
            'devuelto_importe'  => $devueltoTotal,
            'devuelto_en'       => now(),
            'motivo_devolucion' => $motivo,
        ]);

        return true;
    }

    // ------------------------------------------------------------------
    // Connect: alta de la cuenta del salón
    // ------------------------------------------------------------------

    /** Crea la cuenta conectada del salón. Devuelve su id. */
    public function crearCuenta(): string
    {
        $empresa = tenant();

        $respuesta = $this->peticion('post', '/accounts', [
            'type'    => 'express',
            'country' => $empresa->pais ?: 'ES',
            'email'   => $empresa->email,
            'business_type' => 'company',
            'capabilities' => [
                'card_payments' => ['requested' => 'true'],
                'transfers'     => ['requested' => 'true'],
            ],
            'business_profile' => [
                'name' => $empresa->nombre_comercial,
                'url'  => $empresa->urlPortal(),
                'mcc'  => '7230',   // peluquerías y salones de belleza
            ],
            'metadata' => ['empresa_id' => (string) $empresa->id, 'slug' => $empresa->slug],
        ]);

        if ($respuesta->failed()) {
            throw new RuntimeException($respuesta->json('error.message') ?? 'No se pudo crear la cuenta.');
        }

        return $respuesta->json('id');
    }

    /** Enlace para que el salón complete sus datos en Stripe. */
    public function enlaceAlta(string $cuentaId, string $urlVolver, string $urlRefrescar): string
    {
        $respuesta = $this->peticion('post', '/account_links', [
            'account'     => $cuentaId,
            'refresh_url' => $urlRefrescar,
            'return_url'  => $urlVolver,
            'type'        => 'account_onboarding',
        ]);

        if ($respuesta->failed()) {
            throw new RuntimeException($respuesta->json('error.message') ?? 'No se pudo generar el enlace.');
        }

        return $respuesta->json('url');
    }

    /** Estado de la cuenta: si ya puede cobrar o le falta documentación. */
    public function estadoCuenta(string $cuentaId): array
    {
        $respuesta = $this->peticion('get', '/accounts/' . $cuentaId);

        if ($respuesta->failed()) {
            return ['estado' => 'SIN_CONECTAR', 'cobros' => false];
        }

        $cuenta = $respuesta->json();
        $cobros = (bool) ($cuenta['charges_enabled'] ?? false);
        $pendientes = $cuenta['requirements']['currently_due'] ?? [];

        return [
            'estado'      => match (true) {
                $cobros && empty($pendientes) => 'ACTIVA',
                $cobros                       => 'ACTIVA',
                ! empty($cuenta['requirements']['disabled_reason'] ?? null) => 'RESTRINGIDA',
                default                       => 'PENDIENTE',
            },
            'cobros'      => $cobros,
            'pendientes'  => $pendientes,
            'crudo'       => $cuenta,
        ];
    }

    // ------------------------------------------------------------------
    // Webhooks
    // ------------------------------------------------------------------

    /**
     * Verifica la firma del webhook.
     *
     * Sin esto, cualquiera que conozca la URL podría enviar un
     * «pago completado» falso y quedarse con una cita sin pagar.
     */
    public function verificarFirma(string $cuerpo, string $firma): bool
    {
        if (blank($this->secretoWebhook)) {
            return false;
        }

        $partes = [];

        foreach (explode(',', $firma) as $trozo) {
            [$clave, $valor] = array_pad(explode('=', trim($trozo), 2), 2, null);
            $partes[$clave][] = $valor;
        }

        $marca = $partes['t'][0] ?? null;
        $firmas = $partes['v1'] ?? [];

        if (! $marca || $firmas === []) {
            return false;
        }

        // Rechazar eventos de más de cinco minutos: evita reenvíos
        if (abs(time() - (int) $marca) > 300) {
            return false;
        }

        $esperada = hash_hmac('sha256', $marca . '.' . $cuerpo, $this->secretoWebhook);

        foreach ($firmas as $recibida) {
            if (hash_equals($esperada, $recibida)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    protected function peticion(string $metodo, string $ruta, array $datos = [], ?string $cuentaConectada = null): Response
    {
        if (blank($this->clave)) {
            throw new RuntimeException(
                'Falta la clave secreta de Stripe. Añade STRIPE_SECRETO al .env.'
            );
        }

        $peticion = Http::asForm()
            ->withToken($this->clave)
            ->timeout(20)
            ->withHeaders(array_filter([
                'Stripe-Version'  => '2024-06-20',
                'Stripe-Account'  => $cuentaConectada,
            ]));

        return $metodo === 'get'
            ? $peticion->get(self::API . $ruta, $datos)
            : $peticion->post(self::API . $ruta, $this->aplanar($datos));
    }

    /**
     * Stripe no acepta JSON anidado: espera corchetes.
     * ['a' => ['b' => 1]]  →  'a[b]' => 1
     */
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

    /**
     * Los importes van en céntimos, como entero.
     *
     * Se redondea con round() y no con casting: (int)(22.10 * 100) da
     * 2209 en PHP por la representación en coma flotante, y ese céntimo
     * perdido acaba siendo una reclamación.
     */
    protected function aCentimos(float $importe): int
    {
        return (int) round($importe * 100);
    }

    protected function concepto(PagoOnline $pago): string
    {
        $reserva = $pago->reserva;
        $que = $reserva?->resumenServicios() ?: 'Reserva';

        return $pago->tipo === 'FIANZA'
            ? 'Fianza · ' . $que
            : $que;
    }

    protected function traducirEstado(string $pago, string $sesion): string
    {
        return match (true) {
            $pago === 'paid'      => 'PAGADO',
            $sesion === 'expired' => 'CADUCADO',
            $pago === 'unpaid'    => 'INICIADO',
            default               => 'INICIADO',
        };
    }
}
