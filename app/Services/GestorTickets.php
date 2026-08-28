<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Auditoria;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\TicketCobro;
use App\Models\TicketLinea;
use App\Models\Usuario;
use App\Support\SesionSalon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GestorTickets
{
    /**
     * Abre un ticket para el usuario indicado.
     *
     * La serie depende de quien vende: un empleado en formacion emite en
     * la serie FOR, que lleva su propio contador y no consume numeracion
     * fiscal.
     */
    public function abrir(Usuario $usuario, ?Reserva $reserva = null): Ticket
    {
        return DB::transaction(function () use ($usuario, $reserva) {
            // Se normaliza a booleano: el flag puede venir null si el
            // usuario se creo sin pasar el campo.
            $enFormacion = (bool) $usuario->en_formacion;

            $serie = $enFormacion ? Ticket::SERIE_FORMACION : Ticket::SERIE_NORMAL;

            $ticket = Ticket::create([
                'serie'        => $serie,
                'numero'       => Ticket::siguienteNumero($serie),
                'fecha'        => now(),
                'usuario_id'   => $usuario->id,
                'cliente_id'   => $reserva?->cliente_id,
                'reserva_id'   => $reserva?->id,
                'terminal_id'  => SesionSalon::terminal()?->id,
                'estado'       => 'ABIERTO',
                'es_formacion' => $enFormacion,
            ]);

            // Desde una cita: se cargan sus servicios
            if ($reserva) {
                foreach ($reserva->lineas as $linea) {
                    $this->anadirLinea($ticket, $linea->articulo, 1, [
                        'usuario_id'  => $linea->usuario_id,
                        'precio'      => (float) $linea->precio,
                        'descripcion' => $linea->nombre_servicio,
                    ]);
                }
            }

            $ticket = $ticket->fresh('lineas');

            // Lo pagado por internet ya está cobrado
            if ($reserva && $reserva->tieneAnticipo()) {
                $this->aplicarAnticipo($ticket);
            }

            return $ticket->fresh('lineas');
        });
    }

    /**
     * Aplica el anticipo de una reserva como cobro del ticket.
     *
     * Lo que el cliente pagó por internet ya está cobrado: solo hay que
     * reflejarlo para que el TPV pida la diferencia y no el total. Si no
     * se hiciera, se le estaría cobrando dos veces.
     */
    public function aplicarAnticipo(Ticket $ticket): ?TicketCobro
    {
        $reserva = $ticket->reserva;

        if (! $reserva || ! $reserva->tieneAnticipo()) {
            return null;
        }

        // No aplicarlo dos veces si el ticket se reabre
        if ($ticket->cobros()->where('medio', 'ANTICIPO')->exists()) {
            return null;
        }

        $total = (float) $ticket->fresh()->total;
        $restante = min($reserva->anticipo(), $total);

        if ($restante <= 0) {
            return null;
        }

        /**
         * Un cobro por cada pago online, no uno agregado.
         *
         * La trazabilidad tiene que ser 1:1 con el cargo de Stripe: si
         * despues hay que devolver, hay que saber a que cargo lanzar el
         * refund. Con un cobro agregado de «45 €» habria que adivinar de
         * cual de los dos pagos salieron.
         */
        $ultimo = null;

        foreach ($reserva->pagos()->pagados()->orderBy('id')->get() as $pago) {
            if ($restante <= 0.001) {
                break;
            }

            $importe = min($restante, $pago->pendienteDevolver());

            if ($importe <= 0) {
                continue;
            }

            $ultimo = $ticket->cobros()->create([
                'medio'          => 'ANTICIPO',
                'importe'        => round($importe, 2),
                'referencia'     => 'Reserva ' . $reserva->codigo,
                'pago_online_id' => $pago->id,
            ]);

            $restante = round($restante - $importe, 2);
        }

        if ($ultimo && $ticket->fresh()->estaPagado()) {
            $this->cerrar($ticket);
        }

        return $ultimo;
    }

    public function anadirLinea(Ticket $ticket, Articulo $articulo, float $cantidad = 1, array $opciones = []): TicketLinea
    {
        $this->comprobarAbierto($ticket);

        $linea = new TicketLinea([
            'ticket_id'     => $ticket->id,
            'articulo_id'   => $articulo->id,
            'descripcion'   => $opciones['descripcion'] ?? $articulo->nombre,
            'cantidad'      => $cantidad,
            'precio'        => $opciones['precio'] ?? (float) $articulo->precio,
            'dto_pct'       => $opciones['dto_pct'] ?? 0,
            'impuesto_pct'  => (float) $articulo->impuesto_pct,
            'usuario_id'    => $opciones['usuario_id'] ?? $ticket->usuario_id,
            'es_invitacion' => $opciones['es_invitacion'] ?? false,
            'motivo_invitacion' => $opciones['motivo_invitacion'] ?? null,
            'orden'         => ($ticket->lineas()->max('orden') ?? 0) + 1,
        ]);

        $linea->calcular()->save();

        // La coleccion 'lineas' que el objeto tenga cargada en memoria no
        // se entera de esta insercion. Si no se invalida, un foreach
        // posterior recorreria una lista desactualizada.
        $ticket->unsetRelation('lineas');
        $ticket->recalcular();

        return $linea;
    }

    public function quitarLinea(Ticket $ticket, TicketLinea $linea): void
    {
        $this->comprobarAbierto($ticket);

        $linea->delete();
        $ticket->unsetRelation('lineas');
        $ticket->recalcular();
    }

    public function cambiarCantidad(Ticket $ticket, TicketLinea $linea, float $cantidad): void
    {
        $this->comprobarAbierto($ticket);

        if ($cantidad <= 0) {
            $this->quitarLinea($ticket, $linea);

            return;
        }

        $linea->cantidad = $cantidad;
        $linea->calcular()->save();

        $ticket->recalcular();
    }

    public function aplicarDescuento(Ticket $ticket, TicketLinea $linea, float $porcentaje): void
    {
        $this->comprobarAbierto($ticket);

        $linea->dto_pct = max(0, min(100, $porcentaje));
        $linea->calcular()->save();

        $ticket->recalcular();
    }

    public function invitar(Ticket $ticket, TicketLinea $linea, string $motivo): void
    {
        $this->comprobarAbierto($ticket);

        $linea->es_invitacion = true;
        $linea->motivo_invitacion = $motivo;
        $linea->calcular()->save();

        $ticket->recalcular();
        $ticket->update(['es_invitacion' => true]);

        Auditoria::registrar('invitacion', 'ticket_lineas', $linea->id, [
            'ticket' => $ticket->referencia(),
            'motivo' => $motivo,
        ]);
    }

    /**
     * Registra un cobro.
     *
     * REGLA DE FORMACION: un empleado en formacion solo puede cobrar en
     * efectivo. Se comprueba en el servidor, no solo ocultando botones.
     */
    public function cobrar(Ticket $ticket, string $medio, float $importe, ?float $entregado = null, ?string $referencia = null): TicketCobro
    {
        $this->comprobarAbierto($ticket);

        $medio = strtoupper($medio);

        if (! array_key_exists($medio, TicketCobro::MEDIOS)) {
            throw new RuntimeException("Medio de pago desconocido: {$medio}.");
        }

        $usuario = $ticket->usuario;

        if ($usuario && ! $usuario->puedeCobrarCon($medio)) {
            throw new RuntimeException(
                $usuario->estaEnFormacion()
                    ? 'Un empleado en formación solo puede cobrar en efectivo.'
                    : 'Ese medio de pago no está permitido para este usuario.'
            );
        }

        if ($importe <= 0) {
            throw new RuntimeException('El importe del cobro debe ser mayor que cero.');
        }

        $pendiente = $ticket->pendiente();

        if ($importe > $pendiente + 0.001) {
            throw new RuntimeException(
                'El cobro supera lo pendiente (' . number_format($pendiente, 2, ',', '.') . ' €).'
            );
        }

        $cambio = 0.0;

        if ($medio === 'EFECTIVO' && $entregado !== null && $entregado > $importe) {
            $cambio = round($entregado - $importe, 2);
        }

        /**
         * Los medios con saldo mueven dinero REAL antes de registrar el
         * cobro. Si el saldo no llega, el cobro no llega a existir.
         *
         * Al reves —registrar y luego descontar— dejaria el ticket
         * cobrado con un monedero en negativo cuando algo fallara.
         */
        if ($medio === 'MONEDERO') {
            $cliente = $ticket->cliente;

            if (! $cliente) {
                throw new RuntimeException(
                    'Para cobrar del monedero hay que asignar el cliente al ticket.'
                );
            }

            (new GestorBonos())->gastarMonedero($cliente, $importe, $ticket);
        }

        if ($medio === 'VALE') {
            if (blank($referencia)) {
                throw new RuntimeException('Indica el codigo del vale.');
            }

            $gestor = new GestorBonos();
            $vale = $gestor->buscarVale($referencia);

            if (! $vale) {
                throw new RuntimeException('No existe ningun vale con el codigo ' . $referencia . '.');
            }

            $aplicado = $gestor->canjearVale($vale, $importe, $ticket);

            if ($aplicado < $importe - 0.001) {
                throw new RuntimeException(
                    'El vale solo cubre ' . number_format($aplicado, 2, ',', '.') . ' €.'
                );
            }

            $referencia = 'Vale ' . $vale->codigo;
        }

        $cobro = $ticket->cobros()->create([
            'medio'      => $medio,
            'importe'    => round($importe, 2),
            'entregado'  => $entregado,
            'cambio'     => $cambio,
            'referencia' => $referencia,
        ]);

        // Si ya no queda nada pendiente, el ticket se cierra
        if ($ticket->fresh()->estaPagado()) {
            $this->cerrar($ticket);
        }

        return $cobro;
    }

    protected function cerrar(Ticket $ticket): void
    {
        $ticket->update(['estado' => 'COBRADO']);

        /**
         * Descuento de stock.
         *
         * Se consulta la base de datos con lineas()->get() en lugar de
         * usar $ticket->lineas: la relacion pudo cargarse vacia al abrir
         * el ticket, antes de anadir nada, y en ese caso el foreach no
         * recorreria nada y el stock no bajaria nunca.
         */
        foreach ($ticket->lineas()->with('articulo')->get() as $linea) {
            $articulo = $linea->articulo;

            if ($articulo && $articulo->control_stock) {
                $articulo->decrement('stock', (float) $linea->cantidad);
            }
        }

        // La cita queda atendida
        if ($ticket->reserva && $ticket->reserva->estaAbierta()) {
            $ticket->reserva->marcarAtendida();
        }

        /**
         * Emision de los bonos que se hayan vendido en este ticket.
         *
         * Se hace al cerrar, no al anadir la linea: si el cobro no llega
         * a completarse, la clienta se habria llevado un bono sin pagar.
         */
        $this->emitirBonosVendidos($ticket);

        /**
         * Registro VERI*FACTU.
         *
         * Se genera al cobrar, no al enviar: el reglamento exige que el
         * registro exista en el momento de la factura. El envio a la AEAT
         * va en cola aparte, para que una caida de la Agencia no impida
         * cobrar en el salon.
         *
         * Los tickets de formacion quedan fuera: el propio gestor los
         * descarta, porque no son facturas.
         */
        try {
            (new \App\Services\Verifactu\GestorVerifactu())->alta($ticket->fresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('VERI*FACTU: fallo al generar el registro', [
                'ticket' => $ticket->referencia(),
                'error'  => $e->getMessage(),
            ]);
        }

        Auditoria::registrar('ticket_cobrado', 'tickets', $ticket->id, [
            'referencia' => $ticket->referencia(),
            'total'      => (float) $ticket->total,
            'formacion'  => $ticket->es_formacion,
        ]);
    }

    /**
     * Emite un bono por cada linea que venda uno.
     *
     * El bono necesita cliente: sin ficha no hay a quien atribuirselo, y
     * un bono al portador seria un vale, no un bono.
     */
    protected function emitirBonosVendidos(Ticket $ticket): void
    {
        $lineas = $ticket->lineas()->with('articulo.bonoPlantilla')->get()
            ->filter(fn ($linea) => $linea->articulo?->bono_plantilla_id);

        if ($lineas->isEmpty()) {
            return;
        }

        if (! $ticket->cliente) {
            \Illuminate\Support\Facades\Log::warning(
                'Ticket con bono pero sin cliente asignado',
                ['ticket' => $ticket->referencia()],
            );

            return;
        }

        $gestor = new GestorBonos();

        foreach ($lineas as $linea) {
            // Tantos bonos como unidades vendidas
            for ($i = 0; $i < max(1, (int) round((float) $linea->cantidad)); $i++) {
                $gestor->vender($linea->articulo->bonoPlantilla, $ticket->cliente, $ticket);
            }
        }
    }

    public function anular(Ticket $ticket, string $motivo, Usuario $usuario): void
    {
        if (! $ticket->esAnulable()) {
            throw new RuntimeException(
                'No se puede anular: el ticket ya está incluido en un cierre de jornada.'
            );
        }

        DB::transaction(function () use ($ticket, $motivo, $usuario) {
            // Se devuelve el stock. Igual que al cerrar: se consulta
            // la base, nunca la relacion que el objeto tenga cargada.
            $eraCobrado = $ticket->estado === 'COBRADO';

            foreach ($ticket->lineas()->with('articulo')->get() as $linea) {
                $articulo = $linea->articulo;

                if ($articulo && $articulo->control_stock && $eraCobrado) {
                    $articulo->increment('stock', (float) $linea->cantidad);
                }
            }

            $ticket->update([
                'estado'           => 'ANULADO',
                'anulado_por'      => $usuario->id,
                'anulado_en'       => now(),
                'motivo_anulacion' => $motivo,
            ]);
        });

        // Registro de anulacion ante la AEAT
        try {
            (new \App\Services\Verifactu\GestorVerifactu())->anulacion($ticket->fresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('VERI*FACTU: fallo al anular', [
                'ticket' => $ticket->referencia(),
                'error'  => $e->getMessage(),
            ]);
        }

        Auditoria::registrar('ticket_anulado', 'tickets', $ticket->id, [
            'referencia' => $ticket->referencia(),
            'motivo'     => $motivo,
            'total'      => (float) $ticket->total,
        ]);
    }

    protected function comprobarAbierto(Ticket $ticket): void
    {
        if ($ticket->estado !== 'ABIERTO') {
            throw new RuntimeException('El ticket ya está cerrado.');
        }
    }
}
