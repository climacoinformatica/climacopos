<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Ticket;
use App\Models\TicketLinea;
use App\Models\Usuario;
use App\Support\SesionSalon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Devoluciones mediante factura rectificativa.
 *
 * CUÁNDO SE USA CADA COSA
 *
 *   Ticket abierto o del día, sin cerrar   → anular (GestorTickets::anular)
 *   Ticket ya incluido en un cierre        → rectificativa (esto)
 *
 * La diferencia no es un capricho: anular un ticket cerrado descuadraría
 * el arqueo de un día que ya se dio por bueno, y dejaría en la cadena de
 * VERI*FACTU una factura que declaró un importe y ahora dice otro.
 *
 * La rectificativa es un documento NUEVO, con su propio número, que
 * corrige al anterior sin tocarlo.
 */
class GestorDevoluciones
{
    /** Serie propia. No comparte numeración con las facturas normales. */
    public const SERIE = 'R';

    public function __construct(
        protected GestorTickets $tickets = new GestorTickets(),
    ) {
    }

    /**
     * Devolución total de un ticket.
     */
    public function devolverTodo(Ticket $original, string $motivo, ?Usuario $usuario = null): Ticket
    {
        // El usuario se resuelve en devolver(); aqui solo se reparte todo
        $lineas = $original->lineas()->get()
            ->mapWithKeys(fn (TicketLinea $l) => [$l->id => (float) $l->cantidad])
            ->all();

        return $this->devolver($original, $lineas, $motivo, $usuario);
    }

    /**
     * Devolución parcial.
     *
     * @param  array<int, float>  $cantidades  [linea_id => cantidad a devolver]
     */
    public function devolver(Ticket $original, array $cantidades, string $motivo, ?Usuario $usuario = null): Ticket
    {
        /**
         * Quien firma la devolucion.
         *
         * Lo normal es el usuario que tiene la sesion abierta. Pero esto
         * tambien se llama desde comandos y desde los tests, donde no hay
         * sesion: en ese caso responde el usuario que emitio el ticket
         * original, que es quien mejor puede explicarlo.
         */
        $usuario ??= SesionSalon::usuario() ?? $original->usuario;

        if (! $usuario) {
            throw new RuntimeException(
                'No hay ningun usuario al que atribuir la devolucion.'
            );
        }

        $this->comprobar($original, $cantidades);

        return DB::transaction(function () use ($original, $cantidades, $motivo, $usuario) {
            $rectificativa = Ticket::create([
                'serie'                => self::SERIE,
                'numero'               => Ticket::siguienteNumero(self::SERIE),
                'tipo_documento'       => 'RECTIFICATIVA',
                'rectifica_ticket_id'  => $original->id,

                /**
                 * R5: rectificación de facturas simplificadas.
                 * Un ticket de peluquería es una factura simplificada, así
                 * que este es el tipo que corresponde casi siempre.
                 */
                'tipo_rectificativa'   => 'R5',
                'motivo_rectificacion' => $motivo,

                'fecha'        => now(),
                'usuario_id'   => $usuario->id,
                'cliente_id'   => $original->cliente_id,
                'terminal_id'  => SesionSalon::terminal()?->id,
                'estado'       => 'ABIERTO',
                'es_formacion' => false,
            ]);

            $orden = 0;

            foreach ($cantidades as $lineaId => $cantidad) {
                if ($cantidad <= 0) {
                    continue;
                }

                $lineaOriginal = $original->lineas()->findOrFail($lineaId);

                /**
                 * Importes en NEGATIVO.
                 *
                 * Es lo que hace que el documento reste en los informes y en
                 * el libro de facturas sin necesidad de tratarlo aparte.
                 */
                $linea = new TicketLinea([
                    'ticket_id'          => $rectificativa->id,
                    'articulo_id'        => $lineaOriginal->articulo_id,
                    'rectifica_linea_id' => $lineaOriginal->id,
                    'descripcion'        => 'Devolución: ' . $lineaOriginal->descripcion,
                    'cantidad'           => -abs($cantidad),
                    'precio'             => (float) $lineaOriginal->precio,
                    'dto_pct'            => (float) $lineaOriginal->dto_pct,
                    'impuesto_pct'       => (float) $lineaOriginal->impuesto_pct,
                    'usuario_id'         => $lineaOriginal->usuario_id,
                    'orden'              => ++$orden,
                ]);

                $linea->calcular()->save();

                // Devolución de existencias
                $articulo = $lineaOriginal->articulo;

                if ($articulo && $articulo->control_stock) {
                    $articulo->increment('stock', abs($cantidad));
                }
            }

            $rectificativa->unsetRelation('lineas');
            $rectificativa->recalcular();

            $rectificativa = $rectificativa->fresh();

            if ((float) $rectificativa->total >= 0) {
                throw new RuntimeException('La rectificativa no ha generado importe a devolver.');
            }

            Auditoria::registrar('devolucion', 'tickets', $rectificativa->id, [
                'rectificativa' => $rectificativa->referencia(),
                'original'      => $original->referencia(),
                'importe'       => (float) $rectificativa->total,
                'motivo'        => $motivo,
            ], $usuario->id);

            return $rectificativa;
        });
    }

    /**
     * Registra cómo se devuelve el dinero y cierra la rectificativa.
     *
     * El medio no tiene por qué ser el del cobro original: se puede haber
     * cobrado con tarjeta y devolver en efectivo, si el salón lo prefiere.
     * Lo que no se puede es dejarlo sin registrar, o el arqueo no cuadra.
     */
    public function reembolsar(Ticket $rectificativa, string $medio, ?float $importe = null): array
    {
        if ($rectificativa->tipo_documento !== 'RECTIFICATIVA') {
            throw new RuntimeException('Este documento no es una rectificativa.');
        }

        if ($rectificativa->estado !== 'ABIERTO') {
            throw new RuntimeException('Esta devolución ya está cerrada.');
        }

        $total = abs($importe ?? (float) $rectificativa->total);

        /**
         * Primero se devuelve lo que se cobró POR INTERNET, de forma
         * automática y a la misma tarjeta.
         *
         * Lo cobrado en el mostrador —efectivo, datáfono— no se toca: ahí
         * hay una persona que decide cómo devolver, y automatizarlo sería
         * quitarle una decisión que le corresponde. Además, un refund de
         * datáfono no se lanza desde aquí: se hace en el propio terminal.
         */
        $devueltoOnline = $this->devolverOnline($rectificativa, $total);
        $restante = round($total - $devueltoOnline['importe'], 2);

        // El resto, por el medio que haya elegido quien atiende
        if ($restante > 0.001) {
            $rectificativa->cobros()->create([
                'medio'   => strtoupper($medio),
                'importe' => -$restante,
            ]);
        }

        if (abs($rectificativa->fresh()->pendiente()) <= 0.001) {
            $rectificativa->update(['estado' => 'COBRADO']);

            // Registro de VERI*FACTU de la rectificativa
            try {
                (new \App\Services\Verifactu\GestorVerifactu())->alta($rectificativa->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('VERI*FACTU: fallo en rectificativa', [
                    'ticket' => $rectificativa->referencia(),
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return [
            'online'   => $devueltoOnline['importe'],
            'manual'   => max(0, $restante),
            'medio'    => strtoupper($medio),
            'fallidos' => $devueltoOnline['fallidos'],
        ];
    }

    /**
     * Devolución automática de la parte pagada por internet.
     *
     * Se recorren los cobros no presenciales del ticket original y se
     * lanza el refund en la pasarela por cada uno, hasta cubrir lo que se
     * devuelve.
     *
     * @return array{importe: float, fallidos: array<int, string>}
     */
    protected function devolverOnline(Ticket $rectificativa, float $aDevolver): array
    {
        $original = $rectificativa->rectificaA;

        if (! $original) {
            return ['importe' => 0.0, 'fallidos' => []];
        }

        $pasarela = null;
        $devuelto = 0.0;
        $fallidos = [];
        $restante = $aDevolver;

        $cobrosOnline = $original->cobros()
            ->whereIn('medio', \App\Models\TicketCobro::NO_PRESENCIALES)
            ->whereNotNull('pago_online_id')
            ->whereNull('devuelto_por_cobro_id')
            ->with('pagoOnline')
            ->orderBy('id')
            ->get();

        foreach ($cobrosOnline as $cobro) {
            if ($restante <= 0.001) {
                break;
            }

            $pago = $cobro->pagoOnline;

            if (! $pago || ! $pago->esDevolvible()) {
                continue;
            }

            $importe = round(min($restante, (float) $cobro->importe, $pago->pendienteDevolver()), 2);

            if ($importe <= 0) {
                continue;
            }

            $pasarela ??= (new \App\Services\Pagos\GestorPagos())->pasarela();

            try {
                $ok = $pasarela->devolver(
                    $pago,
                    $importe,
                    'Devolución ' . $rectificativa->referencia()
                        . ($rectificativa->motivo_rectificacion
                            ? ': ' . $rectificativa->motivo_rectificacion : ''),
                );
            } catch (\Throwable $e) {
                $ok = false;

                \Illuminate\Support\Facades\Log::error('Fallo al devolver en la pasarela', [
                    'pago'  => $pago->referencia,
                    'error' => $e->getMessage(),
                ]);
            }

            if (! $ok) {
                /**
                 * Si la pasarela falla NO se da por devuelto.
                 *
                 * Registrarlo igualmente dejaría el arqueo cuadrado con un
                 * dinero que el cliente nunca recibió, y nadie se enteraría
                 * hasta la reclamación.
                 */
                $fallidos[] = $pago->referencia;

                continue;
            }

            $nuevoCobro = $rectificativa->cobros()->create([
                'medio'          => 'ANTICIPO',
                'importe'        => -$importe,
                'referencia'     => 'Devolución a ' . $pago->referencia,
                'pago_online_id' => $pago->id,
            ]);

            // Marcado para no devolverlo dos veces
            $cobro->forceFill(['devuelto_por_cobro_id' => $nuevoCobro->id])->save();

            Auditoria::registrar('devolucion_online', 'pagos_online', $pago->id, [
                'rectificativa' => $rectificativa->referencia(),
                'importe'       => $importe,
            ]);

            $devuelto = round($devuelto + $importe, 2);
            $restante = round($restante - $importe, 2);
        }

        return ['importe' => $devuelto, 'fallidos' => $fallidos];
    }

    /**
     * Cuánto de este ticket se cobró por internet y se puede devolver
     * automáticamente. Para avisar en pantalla antes de confirmar.
     */
    public function importeOnlineDevolvible(Ticket $original): float
    {
        return round((float) $original->cobros()
            ->whereIn('medio', \App\Models\TicketCobro::NO_PRESENCIALES)
            ->whereNotNull('pago_online_id')
            ->whereNull('devuelto_por_cobro_id')
            ->sum('importe'), 2);
    }

    // ------------------------------------------------------------------

    protected function comprobar(Ticket $original, array $cantidades): void
    {
        if ($original->es_formacion) {
            throw new RuntimeException(
                'Un documento de formación no se rectifica: no es una factura. '
                . 'Bórralo desde el fichero de formación.'
            );
        }

        if ($original->tipo_documento === 'RECTIFICATIVA') {
            throw new RuntimeException('No se puede rectificar una rectificativa.');
        }

        if ($original->estado !== 'COBRADO') {
            throw new RuntimeException(
                'Solo se pueden devolver tickets cobrados. '
                . ($original->estado === 'ABIERTO'
                    ? 'Este ticket sigue abierto: quita las líneas directamente.'
                    : 'Este ticket está anulado.')
            );
        }

        if ($cantidades === []) {
            throw new RuntimeException('No has indicado qué devolver.');
        }

        /**
         * No se puede devolver más de lo vendido, contando lo ya devuelto
         * en rectificativas anteriores. Sin esta comprobación se podría
         * devolver dos veces el mismo servicio.
         */
        foreach ($cantidades as $lineaId => $cantidad) {
            if ($cantidad <= 0) {
                continue;
            }

            $linea = $original->lineas()->find($lineaId);

            if (! $linea) {
                throw new RuntimeException('Alguna de las líneas no pertenece a este ticket.');
            }

            $yaDevuelto = abs((float) TicketLinea::where('rectifica_linea_id', $linea->id)->sum('cantidad'));
            $disponible = (float) $linea->cantidad - $yaDevuelto;

            if ($cantidad > $disponible + 0.001) {
                throw new RuntimeException(
                    'De «' . $linea->descripcion . '» solo quedan '
                    . rtrim(rtrim(number_format($disponible, 3, ',', ''), '0'), ',')
                    . ' por devolver.'
                );
            }
        }
    }

    /** Cuánto queda por devolver de cada línea. Para la pantalla. */
    public function disponible(Ticket $original): array
    {
        $salida = [];

        foreach ($original->lineas()->get() as $linea) {
            $yaDevuelto = abs((float) TicketLinea::where('rectifica_linea_id', $linea->id)->sum('cantidad'));

            $salida[$linea->id] = [
                'linea'      => $linea,
                'vendido'    => (float) $linea->cantidad,
                'devuelto'   => $yaDevuelto,
                'disponible' => max(0, (float) $linea->cantidad - $yaDevuelto),
            ];
        }

        return $salida;
    }

    public function rectificativasDe(Ticket $original)
    {
        return Ticket::where('rectifica_ticket_id', $original->id)->get();
    }
}
