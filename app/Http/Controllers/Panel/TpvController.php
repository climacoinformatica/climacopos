<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Familia;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\TicketCobro;
use App\Models\TicketLinea;
use App\Services\GestorTickets;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

class TpvController extends Controller
{
    public function __construct(
        protected GestorTickets $gestor = new GestorTickets(),
    ) {
    }

    public function index(Request $peticion)
    {
        $usuario = SesionSalon::usuario();

        // Ticket abierto de este usuario, o uno nuevo
        $ticket = Ticket::conFormacion()
            ->where('usuario_id', $usuario->id)
            ->where('estado', 'ABIERTO')
            ->latest('id')
            ->first();

        if ($peticion->filled('reserva')) {
            $reserva = Reserva::with('lineas.articulo')->findOrFail($peticion->integer('reserva'));

            // Si ya hay un ticket para esa cita, se reutiliza
            $ticket = Ticket::conFormacion()->where('reserva_id', $reserva->id)
                        ->where('estado', 'ABIERTO')->first()
                      ?? $this->gestor->abrir($usuario, $reserva);
        }

        $ticket ??= $this->gestor->abrir($usuario);

        return view('panel.tpv.index', [
            'ticket'    => $ticket->load(['lineas.articulo', 'cliente', 'cobros']),
            'familias'  => Familia::activas()->with(['articulos' => fn ($q) => $q->activos()->orderBy('orden')])
                            ->orderBy('orden')->get(),
            'usuario'   => $usuario,
            'medios'    => $usuario->mediosPagoPermitidos(),
            'citasHoy'  => Reserva::with('lineas')
                            ->delDia(now())
                            ->whereIn('estado', ['CONFIRMADA', 'EN_CURSO'])
                            ->whereDoesntHave('ticket')
                            ->orderBy('hora_ini')
                            ->get(),
        ]);
    }

    public function anadir(Request $peticion, Ticket $ticket)
    {
        $datos = $peticion->validate([
            'articulo_id' => ['required', 'exists:articulos,id'],
            'cantidad'    => ['nullable', 'numeric', 'min:0.001'],
        ]);

        try {
            $this->gestor->anadirLinea(
                $ticket,
                Articulo::findOrFail($datos['articulo_id']),
                (float) ($datos['cantidad'] ?? 1),
            );
        } catch (\RuntimeException $e) {
            return $this->respuesta($peticion, false, $e->getMessage(), $ticket);
        }

        return $this->respuesta($peticion, true, null, $ticket);
    }

    public function cantidad(Request $peticion, Ticket $ticket, TicketLinea $linea)
    {
        $peticion->validate(['cantidad' => ['required', 'numeric', 'min:0']]);

        $this->gestor->cambiarCantidad($ticket, $linea, (float) $peticion->input('cantidad'));

        return $this->respuesta($peticion, true, null, $ticket);
    }

    public function quitarLinea(Request $peticion, Ticket $ticket, TicketLinea $linea)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario->tienePermiso(Permisos::TPV_ANULAR_LINEA)) {
            return $this->respuesta($peticion, false, 'Tu perfil no permite quitar líneas.', $ticket);
        }

        $this->gestor->quitarLinea($ticket, $linea);

        return $this->respuesta($peticion, true, null, $ticket);
    }

    public function descuento(Request $peticion, Ticket $ticket, TicketLinea $linea)
    {
        $peticion->validate(['porcentaje' => ['required', 'numeric', 'min:0', 'max:100']]);

        $usuario = SesionSalon::usuario();

        if (! $usuario->tienePermiso(Permisos::TPV_DESCUENTO)) {
            return $this->respuesta($peticion, false, 'Tu perfil no permite aplicar descuentos.', $ticket);
        }

        $this->gestor->aplicarDescuento($ticket, $linea, (float) $peticion->input('porcentaje'));

        return $this->respuesta($peticion, true, null, $ticket);
    }

    public function invitar(Request $peticion, Ticket $ticket, TicketLinea $linea)
    {
        $peticion->validate(['motivo' => ['required', 'string', 'max:160']]);

        $usuario = SesionSalon::usuario();

        if (! $usuario->tienePermiso(Permisos::TPV_INVITACION)) {
            return $this->respuesta($peticion, false, 'Tu perfil no permite invitar.', $ticket);
        }

        $this->gestor->invitar($ticket, $linea, $peticion->string('motivo')->toString());

        return $this->respuesta($peticion, true, null, $ticket);
    }

    /**
     * Bonos con los que el cliente del ticket puede pagar esta linea.
     *
     * Se consulta al anadir cada linea. Si la clienta tiene un bono que
     * cubre el servicio, el TPV lo ofrece: preguntarlo es lo que evita
     * que se le cobre dos veces por algo que ya pago.
     */
    public function bonosDisponibles(Ticket $ticket, TicketLinea $linea)
    {
        $cliente = $ticket->cliente;

        if (! $cliente || ! $linea->articulo) {
            return response()->json(['bonos' => []]);
        }

        $bonos = (new \App\Services\GestorBonos())
            ->bonosPara($cliente, $linea->articulo);

        return response()->json([
            'bonos' => $bonos->map(fn ($bono) => [
                'id'      => $bono->id,
                'codigo'  => $bono->codigo,
                'nombre'  => $bono->plantilla->nombre,
                'resumen' => $bono->resumen(),
                'caduca'  => $bono->caduca_el?->format('d/m/Y'),
            ])->values(),
        ]);
    }

    public function usarBono(Request $peticion, Ticket $ticket, TicketLinea $linea)
    {
        $peticion->validate(['bono_id' => ['required', 'exists:bonos,id']]);

        try {
            (new \App\Services\GestorBonos())->consumir(
                \App\Models\Bono::findOrFail($peticion->integer('bono_id')),
                $linea,
                SesionSalon::usuario(),
            );
        } catch (\RuntimeException $e) {
            return $this->respuesta($peticion, false, $e->getMessage(), $ticket);
        }

        return $this->respuesta($peticion, true, null, $ticket);
    }

    /**
     * Buscador de clientes del TPV.
     *
     * Busca por nombre, apellidos, telefono y email a la vez. Quien esta
     * en el mostrador escribe lo primero que recuerda —normalmente los
     * cuatro ultimos digitos del movil— y no deberia tener que elegir
     * antes por que campo busca.
     */
    public function buscarClientes(Request $peticion)
    {
        $texto = trim($peticion->input('q', ''));

        if (mb_strlen($texto) < 2) {
            return response()->json(['clientes' => []]);
        }

        $clientes = \App\Models\Cliente::query()
            ->where('bloqueado', false)
            ->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', "%{$texto}%")
                  ->orWhere('apellidos', 'like', "%{$texto}%")
                  ->orWhere('telefono', 'like', "%{$texto}%")
                  ->orWhere('email', 'like', "%{$texto}%")
                  // Nombre y apellidos juntos: «maria lopez» no encuentra
                  // nada si se buscan por separado
                  ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellidos, '')) LIKE ?", ["%{$texto}%"]);
            })
            ->orderByDesc('ultima_visita')
            ->limit(15)
            ->get();

        return response()->json([
            'clientes' => $clientes->map(fn ($cliente) => $this->resumirCliente($cliente))->values(),
        ]);
    }

    /**
     * Alta rapida desde el TPV.
     *
     * Solo nombre y telefono: pedir mas datos con una clienta esperando
     * hace que se acabe pulsando «sin cliente», y entonces se pierden el
     * historial, los bonos y cualquier posibilidad de fidelizar.
     */
    public function crearCliente(Request $peticion, Ticket $ticket)
    {
        $datos = $peticion->validate([
            'nombre'    => ['required', 'string', 'max:80'],
            'apellidos' => ['nullable', 'string', 'max:120'],
            'telefono'  => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:160'],
        ]);

        // Si ya existe alguien con ese telefono, se reutiliza en vez de
        // crear un duplicado que ensucia el fichero para siempre
        if (filled($datos['telefono'] ?? null)) {
            $existente = \App\Models\Cliente::where('telefono', $datos['telefono'])->first();

            if ($existente) {
                $ticket->update(['cliente_id' => $existente->id]);

                return response()->json([
                    'ok'      => true,
                    'aviso'   => 'Ya habia una ficha con ese telefono: se ha usado esa.',
                    'cliente' => $this->resumirCliente($existente),
                    'ticket'  => $this->serializar($ticket->fresh(['lineas.articulo', 'cobros', 'cliente'])),
                ]);
            }
        }

        $cliente = \App\Models\Cliente::create($datos + ['fecha_alta' => now()]);

        $ticket->update(['cliente_id' => $cliente->id]);

        return response()->json([
            'ok'      => true,
            'cliente' => $this->resumirCliente($cliente),
            'ticket'  => $this->serializar($ticket->fresh(['lineas.articulo', 'cobros', 'cliente'])),
        ]);
    }

    /** Lo que el TPV necesita saber de un cliente. */
    protected function resumirCliente(\App\Models\Cliente $cliente): array
    {
        $bonos = $cliente->bonosActivos()->with('plantilla')->get();

        return [
            'id'        => $cliente->id,
            'nombre'    => $cliente->nombreCompleto(),
            'telefono'  => $cliente->telefono,
            'email'     => $cliente->email,
            'visitas'   => (int) ($cliente->citas_totales ?? 0),
            'ultima'    => $cliente->ultima_visita?->format('d/m/Y'),
            'saldo'     => round((float) $cliente->saldo_monedero, 2),
            'bonos'     => $bonos->map(fn ($bono) => [
                'nombre'  => $bono->plantilla?->nombre,
                'resumen' => $bono->resumen(),
            ])->values(),
            'avisos'    => $cliente->avisos_ficha ?? null,
        ];
    }

    public function cliente(Request $peticion, Ticket $ticket)
    {
        $peticion->validate(['cliente_id' => ['nullable', 'exists:clientes,id']]);

        $ticket->update(['cliente_id' => $peticion->input('cliente_id')]);

        $ticket = $ticket->fresh(['lineas.articulo', 'cobros', 'cliente']);

        /**
         * Al asignar cliente se comprueban las lineas YA anadidas.
         *
         * Lo normal es teclear primero los servicios y asignar la ficha al
         * cobrar. Si solo se miraran los bonos al anadir cada linea, en
         * ese orden no se ofreceria ninguno.
         */
        $conBono = [];

        if ($ticket->cliente) {
            $gestor = new \App\Services\GestorBonos();

            foreach ($ticket->lineas as $linea) {
                if ($linea->bono_id || ! $linea->articulo) {
                    continue;
                }

                $bonos = $gestor->bonosPara($ticket->cliente, $linea->articulo);

                if ($bonos->isNotEmpty()) {
                    $conBono[] = [
                        'linea_id'    => $linea->id,
                        'descripcion' => $linea->descripcion,
                        'bonos'       => $bonos->map(fn ($bono) => [
                            'id'      => $bono->id,
                            'nombre'  => $bono->plantilla->nombre,
                            'resumen' => $bono->resumen(),
                        ])->values(),
                    ];
                }
            }
        }

        return response()->json([
            'ok'       => true,
            'ticket'   => $this->serializar($ticket),
            'cliente'  => $ticket->cliente ? $this->resumirCliente($ticket->cliente) : null,
            'con_bono' => $conBono,
        ]);
    }

    public function cobrar(Request $peticion, Ticket $ticket)
    {
        $datos = $peticion->validate([
            'medio'      => ['required', 'string'],
            'importe'    => ['required', 'numeric', 'min:0.01'],
            'entregado'  => ['nullable', 'numeric', 'min:0'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $cobro = $this->gestor->cobrar(
                $ticket,
                $datos['medio'],
                (float) $datos['importe'],
                isset($datos['entregado']) ? (float) $datos['entregado'] : null,
                $datos['referencia'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return $this->respuesta($peticion, false, $e->getMessage(), $ticket);
        }

        $ticket = $ticket->fresh(['lineas', 'cobros']);

        $cerrado = $ticket->estado === 'COBRADO';

        /**
         * Impresion del ticket.
         *
         * Solo cuando la venta queda cerrada: en un cobro parcial el
         * documento todavia puede cambiar, e imprimirlo dos veces
         * confunde a la clienta y gasta papel.
         *
         * El modo lo decide el TERMINAL, no el salon: un mismo negocio
         * puede tener impresora en caja y una tablet sin ella.
         *
         *   SIEMPRE    se imprime solo
         *   PREGUNTAR  el TPV ofrece el boton, decide quien atiende
         *   NUNCA      solo a peticion, desde el boton de reimprimir
         */
        $modoImpresion = 'NUNCA';

        if ($cerrado) {
            $terminal = SesionSalon::terminal();
            $modoImpresion = $terminal?->ajuste('ticket_imprimir', 'SIEMPRE') ?: 'SIEMPRE';

            if ($modoImpresion === 'SIEMPRE') {
                $this->imprimirTicket($ticket);
            }
        }

        return response()->json([
            'ok'         => true,
            'cambio'     => (float) $cobro->cambio,
            'pendiente'  => $ticket->pendiente(),
            'cerrado'    => $cerrado,
            'referencia' => $ticket->referencia(),
            'ticket'     => $this->serializar($ticket),

            // El JavaScript decide si enseña el boton de imprimir
            'impresion'  => $modoImpresion,
        ]);
    }

    /**
     * Envia el ticket a la cola de impresion.
     *
     * Los fallos NO se propagan: un problema con la impresora no puede
     * tumbar un cobro que ya esta hecho y registrado en Hacienda. Se
     * anota en el registro y el ticket queda disponible para reimprimir.
     */
    protected function imprimirTicket(Ticket $ticket, bool $esCopia = false): bool
    {
        try {
            $trabajo = (new \App\Services\GestorImpresion())
                ->ticket($ticket, SesionSalon::terminal(), $esCopia);

            return $trabajo !== null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo encolar el ticket', [
                'ticket' => $ticket->referencia(),
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Imprimir o reimprimir a peticion.
     *
     * Sirve para los tres modos: el boton de «Imprimir» cuando esta en
     * PREGUNTAR, y el de reimprimir cuando la clienta lo pide despues.
     */
    public function imprimir(Request $peticion, Ticket $ticket)
    {
        if ($ticket->estado !== 'COBRADO') {
            return $this->respuesta($peticion, false,
                'El ticket todavia no esta cobrado.', $ticket);
        }

        $esCopia = $peticion->boolean('copia');

        if (! $this->imprimirTicket($ticket, $esCopia)) {
            return $this->respuesta($peticion, false,
                'No se ha podido enviar a la impresora. Revisa que el agente '
                . 'este funcionando en este equipo.', $ticket);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => $esCopia ? 'Copia enviada a la impresora.'
                                  : 'Ticket enviado a la impresora.',
        ]);
    }

    public function nuevo()
    {
        $ticket = $this->gestor->abrir(SesionSalon::usuario());

        return redirect()->route('panel.tpv', ['ticket' => $ticket->id]);
    }

    public function anular(Request $peticion, Ticket $ticket)
    {
        $peticion->validate(['motivo' => ['required', 'string', 'max:255']]);

        try {
            $this->gestor->anular($ticket, $peticion->string('motivo')->toString(), SesionSalon::usuario());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('panel.tpv')->with('exito', 'Ticket anulado.');
    }

    /** Listado de tickets del día */
    public function tickets(Request $peticion)
    {
        $tickets = Ticket::with(['usuario', 'cobros'])
            ->delDia($peticion->input('fecha', now()->toDateString()))
            ->orderByDesc('id')
            ->paginate(50);

        return view('panel.tpv.tickets', [
            'tickets' => $tickets,
            'fecha'   => $peticion->input('fecha', now()->toDateString()),
        ]);
    }

    // ------------------------------------------------------------------

    protected function respuesta(Request $peticion, bool $ok, ?string $error, Ticket $ticket)
    {
        $ticket = $ticket->fresh(['lineas.articulo', 'cobros', 'cliente']);

        if ($peticion->expectsJson()) {
            return response()->json([
                'ok'     => $ok,
                'error'  => $error,
                'ticket' => $this->serializar($ticket),
            ], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'exito' : 'error', $error ?? 'Hecho.');
    }

    protected function serializar(Ticket $ticket): array
    {
        return [
            'id'         => $ticket->id,
            'referencia' => $ticket->referencia(),
            'estado'     => $ticket->estado,
            'formacion'  => $ticket->es_formacion,
            'base'       => (float) $ticket->base,
            'impuesto'   => (float) $ticket->impuesto,
            'total'      => (float) $ticket->total,
            'pendiente'  => $ticket->pendiente(),
            'cliente'    => $ticket->cliente?->nombreCompleto(),
            'cliente_id' => $ticket->cliente_id,

            // Lo que el cliente ya tiene pagado, para ofrecerlo al cobrar
            'saldo'      => $ticket->cliente
                            ? round((float) $ticket->cliente->saldo_monedero, 2) : 0,
            'tiene_bonos'=> (bool) $ticket->cliente?->bonosActivos()->exists(),
            'lineas'     => $ticket->lineas->map(fn (TicketLinea $l) => [
                'id'          => $l->id,
                'descripcion' => $l->descripcion,
                'cantidad'    => (float) $l->cantidad,
                'precio'      => (float) $l->precio,
                'dto'         => (float) $l->dto_pct,
                'importe'     => (float) $l->importe,
                'invitacion'  => $l->es_invitacion,
                'bono'        => $l->bono_id ? $l->bono?->codigo : null,
            ])->values(),
            'cobros'     => $ticket->cobros->map(fn (TicketCobro $c) => [
                'medio'   => $c->nombreMedio(),
                'importe' => (float) $c->importe,
            ])->values(),
        ];
    }
}
