<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Crea en Stripe los productos y precios de cada plan.
 *
 * POR QUÉ AUTOMÁTICO Y NO A MANO
 *
 * La alternativa era crearlos en el panel de Stripe y copiar los
 * identificadores —del tipo `price_1A2b3C...`— en cada plan. Con diez
 * planes son veinte identificadores que copiar, y basta equivocarse en
 * uno para que un salón acabe pagando el precio de otro plan.
 *
 * Aquí se crean por API y los identificadores se guardan solos.
 *
 * LOS PRECIOS DE STRIPE NO SE EDITAN
 *
 * Es una decisión suya, no una limitación: un precio ya cobrado no puede
 * cambiar, porque rompería el histórico de facturación de quien lo tenga
 * contratado. Al cambiar el importe se crea un precio nuevo y el
 * anterior queda archivado, respetando a quien ya estaba suscrito.
 */
class SincronizadorStripe
{
    protected const API = 'https://api.stripe.com/v1';

    /**
     * Crea o actualiza en Stripe todo lo que haga falta para un plan.
     *
     * @return array{producto: string, mes: ?string, ano: ?string}
     */
    public function sincronizar(Plan $plan): array
    {
        /**
         * Los planes gratuitos no van a Stripe.
         *
         * No hay nada que cobrar, y crear un precio de cero euros solo
         * serviría para que el ciclo de morosidad intentara cobrarlo cada
         * mes y marcara al salón como impagado.
         */
        if ($plan->es_gratuito || (float) $plan->precio_mes <= 0) {
            return ['producto' => '', 'mes' => null, 'ano' => null];
        }

        $producto = $this->producto($plan);

        $resultado = [
            'producto' => $producto,
            'mes'      => null,
            'ano'      => null,
        ];

        $cambios = [];

        // ---- Precio mensual
        if ($this->precioCambio($plan->stripe_price_mes, (float) $plan->precio_mes)) {
            $resultado['mes'] = $this->crearPrecio(
                $producto, (float) $plan->precio_mes, 'month', $plan,
            );

            $cambios['stripe_price_mes'] = $resultado['mes'];
        } else {
            $resultado['mes'] = $plan->stripe_price_mes;
        }

        // ---- Precio anual
        if ((float) $plan->precio_ano > 0) {
            if ($this->precioCambio($plan->stripe_price_ano, (float) $plan->precio_ano)) {
                $resultado['ano'] = $this->crearPrecio(
                    $producto, (float) $plan->precio_ano, 'year', $plan,
                );

                $cambios['stripe_price_ano'] = $resultado['ano'];
            } else {
                $resultado['ano'] = $plan->stripe_price_ano;
            }
        }

        if ($cambios !== []) {
            $plan->forceFill($cambios)->save();
        }

        return $resultado;
    }

    /** Sincroniza todos los planes de pago que estén activos. */
    public function sincronizarTodos(): array
    {
        $hechos = [];
        $fallos = [];

        foreach (Plan::where('activo', true)->where('es_gratuito', false)->get() as $plan) {
            try {
                $this->sincronizar($plan);

                $hechos[] = $plan->nombreCompleto();
            } catch (\Throwable $e) {
                $fallos[$plan->nombreCompleto()] = $e->getMessage();

                Log::error('No se pudo sincronizar el plan con Stripe', [
                    'plan'  => $plan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['hechos' => $hechos, 'fallos' => $fallos];
    }

    // ------------------------------------------------------------------

    /**
     * El producto de Stripe correspondiente al plan.
     *
     * Se guarda su identificador para no crear uno nuevo cada vez que se
     * toca un precio: el producto es el concepto —«Plan Básico de CLIMACO
     * POS Beauty»— y los precios son las tarifas que cuelgan de él.
     */
    protected function producto(Plan $plan): string
    {
        if (filled($plan->stripe_producto)) {
            // Se comprueba que siga existiendo: alguien puede haberlo
            // borrado desde el panel de Stripe
            $respuesta = $this->peticion('get', '/products/' . $plan->stripe_producto);

            if ($respuesta->successful()) {
                return $plan->stripe_producto;
            }
        }

        $respuesta = $this->peticion('post', '/products', [
            'name'        => $plan->nombreCompleto(),
            'description' => $plan->descripcion ?: $plan->soporteLegible(),

            // Para reconocerlo desde el panel de Stripe
            'metadata' => [
                'plan_id'  => $plan->id,
                'producto' => $plan->producto?->slug,
                'slug'     => $plan->slug,
            ],
        ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException($this->error($respuesta));
        }

        $id = $respuesta->json('id');

        $plan->forceFill(['stripe_producto' => $id])->save();

        return $id;
    }

    protected function crearPrecio(string $producto, float $importe, string $intervalo, Plan $plan): string
    {
        $respuesta = $this->peticion('post', '/prices', [
            'product'     => $producto,
            'currency'    => 'eur',

            // Stripe trabaja en céntimos: 18,00 € son 1800
            'unit_amount' => (int) round($importe * 100),

            'recurring'   => ['interval' => $intervalo],

            'metadata' => [
                'plan_id' => $plan->id,
                'ciclo'   => $intervalo === 'month' ? 'MENSUAL' : 'ANUAL',
            ],
        ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException($this->error($respuesta));
        }

        return $respuesta->json('id');
    }

    /**
     * ¿Hay que crear un precio nuevo?
     *
     * Si no existe, sí. Y si el importe de Stripe no coincide con el del
     * plan, también: los precios de Stripe no se pueden editar, así que
     * cambiar el importe significa crear otro.
     */
    protected function precioCambio(?string $precioId, float $importe): bool
    {
        if (blank($precioId)) {
            return true;
        }

        $respuesta = $this->peticion('get', '/prices/' . $precioId);

        if (! $respuesta->successful()) {
            return true;
        }

        return (int) $respuesta->json('unit_amount') !== (int) round($importe * 100);
    }

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

        return $metodo === 'get'
            ? $peticion->get(self::API . $ruta, $datos)
            : $peticion->post(self::API . $ruta, $this->aplanar($datos));
    }

    /** Stripe devuelve mensajes claros: se pasan tal cual. */
    protected function error($respuesta): string
    {
        return $respuesta->json('error.message')
            ?? 'Stripe respondió con un error ' . $respuesta->status();
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
