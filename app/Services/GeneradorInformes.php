<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\TicketCobro;
use App\Models\TicketLinea;
use App\Models\Usuario;
use App\Support\Intervalo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generador de informes.
 *
 * IMPORTANTE: todas las consultas parten del modelo Ticket, que lleva el
 * global scope ExcluirFormacion. Los documentos de prácticas quedan fuera
 * de todos los informes sin que haya que acordarse de filtrarlos.
 *
 * Los tickets ANULADOS también se excluyen siempre: un ticket anulado no
 * es una venta, y contarlo infla la facturación.
 */
class GeneradorInformes
{
    public function __construct(
        public readonly Carbon $desde,
        public readonly Carbon $hasta,
    ) {
    }

    public static function entre(string|Carbon $desde, string|Carbon $hasta): self
    {
        return new self(
            Carbon::parse($desde)->startOfDay(),
            Carbon::parse($hasta)->endOfDay(),
        );
    }

    // ------------------------------------------------------------------
    // Base
    // ------------------------------------------------------------------

    protected function tickets()
    {
        return Ticket::query()
            ->whereBetween('fecha', [$this->desde, $this->hasta])
            ->where('estado', 'COBRADO');
    }

    protected function lineas()
    {
        return TicketLinea::query()
            ->whereIn('ticket_id', $this->tickets()->select('id'));
    }

    public function dias(): int
    {
        return max(1, (int) $this->desde->diffInDays($this->hasta) + 1);
    }

    // ------------------------------------------------------------------
    // Resumen
    // ------------------------------------------------------------------

    public function resumen(): array
    {
        $tickets = $this->tickets()->get();
        $lineas  = $this->lineas()->get();

        $numTickets = $tickets->count();
        $ventas     = round($tickets->sum('total'), 2);

        // El periodo anterior, del mismo número de días, para comparar
        $anterior = self::entre(
            $this->desde->copy()->subDays($this->dias()),
            $this->desde->copy()->subDay(),
        );

        $ventasAnterior = round($anterior->tickets()->sum('total'), 2);

        return [
            'ventas'          => $ventas,
            'base'            => round($tickets->sum('base'), 2),
            'impuesto'        => round($tickets->sum('impuesto'), 2),
            'num_tickets'     => $numTickets,
            'ticket_medio'    => $numTickets > 0 ? round($ventas / $numTickets, 2) : 0.0,
            'articulos'       => round($lineas->sum('cantidad'), 2),
            'venta_diaria'    => round($ventas / $this->dias(), 2),
            'ventas_anterior' => $ventasAnterior,
            'variacion'       => $ventasAnterior > 0
                                 ? round((($ventas - $ventasAnterior) / $ventasAnterior) * 100, 1)
                                 : null,
        ];
    }

    // ------------------------------------------------------------------
    // Evolución
    // ------------------------------------------------------------------

    public function porDia(): array
    {
        $datos = $this->tickets()
            ->selectRaw('DATE(fecha) as dia, COUNT(*) as tickets, SUM(total) as total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $salida = [];
        $fecha = $this->desde->copy();

        // Se rellenan los días sin ventas: un hueco en la serie
        // hace pensar que faltan datos, no que no se vendió.
        while ($fecha->lte($this->hasta)) {
            $clave = $fecha->toDateString();
            $fila = $datos->get($clave);

            $salida[] = [
                'fecha'   => $fecha->copy(),
                'etiqueta' => $fecha->locale('es')->isoFormat('ddd D'),
                'tickets' => (int) ($fila->tickets ?? 0),
                'total'   => round((float) ($fila->total ?? 0), 2),
            ];

            $fecha->addDay();
        }

        return $salida;
    }

    public function porHora(): array
    {
        $datos = $this->tickets()
            ->selectRaw('HOUR(fecha) as hora, COUNT(*) as tickets, SUM(total) as total')
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->keyBy('hora');

        $salida = [];

        for ($h = 7; $h <= 22; $h++) {
            $fila = $datos->get($h);

            $salida[] = [
                'etiqueta' => sprintf('%02d:00', $h),
                'tickets'  => (int) ($fila->tickets ?? 0),
                'total'    => round((float) ($fila->total ?? 0), 2),
            ];
        }

        return $salida;
    }

    public function porDiaSemana(): array
    {
        $nombres = [1 => 'Domingo', 2 => 'Lunes', 3 => 'Martes', 4 => 'Miércoles',
                    5 => 'Jueves', 6 => 'Viernes', 7 => 'Sábado'];

        $datos = $this->tickets()
            ->selectRaw('DAYOFWEEK(fecha) as dia, COUNT(*) as tickets, SUM(total) as total')
            ->groupBy('dia')
            ->get()
            ->keyBy('dia');

        $salida = [];

        foreach ([2, 3, 4, 5, 6, 7, 1] as $dia) {
            $fila = $datos->get($dia);

            $salida[] = [
                'etiqueta' => $nombres[$dia],
                'tickets'  => (int) ($fila->tickets ?? 0),
                'total'    => round((float) ($fila->total ?? 0), 2),
            ];
        }

        return $salida;
    }

    // ------------------------------------------------------------------
    // Catálogo
    // ------------------------------------------------------------------

    public function porFamilia(): array
    {
        return $this->lineas()
            ->leftJoin('articulos', 'ticket_lineas.articulo_id', '=', 'articulos.id')
            ->leftJoin('familias', 'articulos.familia_id', '=', 'familias.id')
            ->selectRaw('COALESCE(familias.nombre, "Sin familia") as etiqueta,
                         familias.color as color,
                         SUM(ticket_lineas.cantidad) as unidades,
                         SUM(ticket_lineas.importe) as total')
            ->groupBy('etiqueta', 'color')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f) => [
                'etiqueta' => $f->etiqueta,
                'color'    => $f->color ?? '#6366f1',
                'unidades' => round((float) $f->unidades, 2),
                'total'    => round((float) $f->total, 2),
            ])->all();
    }

    public function porArticulo(int $limite = 30): array
    {
        return $this->lineas()
            ->selectRaw('ticket_lineas.descripcion as etiqueta,
                         SUM(ticket_lineas.cantidad) as unidades,
                         SUM(ticket_lineas.importe) as total')
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($a) => [
                'etiqueta' => $a->etiqueta,
                'unidades' => round((float) $a->unidades, 2),
                'total'    => round((float) $a->total, 2),
            ])->all();
    }

    public function serviciosVsProductos(): array
    {
        $datos = $this->lineas()
            ->leftJoin('articulos', 'ticket_lineas.articulo_id', '=', 'articulos.id')
            ->selectRaw('CASE WHEN articulos.tipo = "PRODUCTO" THEN "Productos" ELSE "Servicios" END as etiqueta,
                         SUM(ticket_lineas.importe) as total')
            ->groupBy('etiqueta')
            ->get();

        return $datos->map(fn ($d) => [
            'etiqueta' => $d->etiqueta,
            'color'    => $d->etiqueta === 'Productos' ? '#f59e0b' : '#6366f1',
            'total'    => round((float) $d->total, 2),
        ])->all();
    }

    // ------------------------------------------------------------------
    // Personas
    // ------------------------------------------------------------------

    /**
     * Ventas y comision por profesional.
     *
     * Con $usuarioId se limita a uno solo. El filtro va en el WHERE y no
     * recortando el resultado despues, para que los totales del propio
     * informe cuadren con lo que se ve.
     */
    public function porProfesional(?int $usuarioId = null): array
    {
        return $this->lineas()
            ->leftJoin('usuarios', 'ticket_lineas.usuario_id', '=', 'usuarios.id')
            ->when($usuarioId, fn ($q) => $q->where('ticket_lineas.usuario_id', $usuarioId))
            ->selectRaw('COALESCE(usuarios.nombre, "Sin asignar") as etiqueta,
                         usuarios.color_agenda as color,
                         usuarios.comision_pct as comision,
                         COUNT(DISTINCT ticket_lineas.ticket_id) as tickets,
                         SUM(ticket_lineas.cantidad) as unidades,
                         SUM(ticket_lineas.importe) as total')
            ->groupBy('etiqueta', 'color', 'comision')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($p) => [
                'etiqueta'  => $p->etiqueta,
                'color'     => $p->color ?? '#6366f1',
                'tickets'   => (int) $p->tickets,
                'unidades'  => round((float) $p->unidades, 2),
                'total'     => round((float) $p->total, 2),
                'comision'  => round((float) $p->total * ((float) $p->comision / 100), 2),
                'comision_pct' => (float) $p->comision,
            ])->all();
    }

    public function porMedioPago(): array
    {
        return TicketCobro::query()
            ->whereIn('ticket_id', $this->tickets()->select('id'))
            ->selectRaw('medio as etiqueta, COUNT(*) as veces, SUM(importe) as total')
            ->groupBy('medio')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($m) => [
                'etiqueta' => TicketCobro::MEDIOS[$m->etiqueta] ?? $m->etiqueta,
                'veces'    => (int) $m->veces,
                'total'    => round((float) $m->total, 2),
            ])->all();
    }

    // ------------------------------------------------------------------
    // Clientes
    // ------------------------------------------------------------------

    public function clientes(): array
    {
        $conCliente = $this->tickets()->whereNotNull('cliente_id')->count();
        $total      = $this->tickets()->count();

        /*
         * La columna fecha_alta la crea la migracion
         * 2026_08_30_120000_clientes_fecha_alta. Antes no existia y este
         * recuento tumbaba el informe entero con un error 500.
         *
         * Se usa fecha_alta y no created_at porque al importar clientes
         * de otro programa el alta real no es el dia en que se creo la
         * fila. La migracion rellena los antiguos con created_at.
         */
        $nuevos = Cliente::whereBetween('fecha_alta', [$this->desde, $this->hasta])->count();

        // Clientes que ya habían venido antes del periodo
        $recurrentes = Cliente::whereHas('reservas', function ($q) {
            $q->where('fecha', '<', $this->desde->toDateString())
              ->where('estado', 'ATENDIDA');
        })->whereHas('reservas', function ($q) {
            $q->whereBetween('fecha', [$this->desde->toDateString(), $this->hasta->toDateString()]);
        })->count();

        return [
            'nuevos'          => $nuevos,
            'recurrentes'     => $recurrentes,
            'tickets_con_ficha' => $conCliente,
            'tickets_sin_ficha' => $total - $conCliente,
            'pct_identificados' => $total > 0 ? round(($conCliente / $total) * 100, 1) : 0,
        ];
    }

    /** Clientes que no vienen desde hace tiempo. Para campañas. */
    public function clientesInactivos(int $meses = 6, int $limite = 100): array
    {
        return Cliente::whereNotNull('ultima_visita')
            ->where('ultima_visita', '<', now()->subMonths($meses))
            ->where('bloqueado', false)
            ->orderBy('ultima_visita')
            ->limit($limite)
            ->get()
            ->map(fn (Cliente $c) => [
                'etiqueta' => $c->nombreCompleto(),
                'telefono' => $c->telefono,
                'email'    => $c->email,
                'ultima'   => $c->ultima_visita?->format('d/m/Y'),
                'meses'    => (int) $c->ultima_visita?->diffInMonths(now()),
                'visitas'  => $c->citas_totales,
            ])->all();
    }

    public function mejoresClientes(int $limite = 20): array
    {
        return $this->tickets()
            ->whereNotNull('cliente_id')
            ->leftJoin('clientes', 'tickets.cliente_id', '=', 'clientes.id')
            ->selectRaw('CONCAT(clientes.nombre, " ", COALESCE(clientes.apellidos, "")) as etiqueta,
                         clientes.telefono as telefono,
                         COUNT(*) as visitas,
                         SUM(tickets.total) as total')
            ->groupBy('etiqueta', 'telefono')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($c) => [
                'etiqueta' => trim($c->etiqueta),
                'telefono' => $c->telefono,
                'visitas'  => (int) $c->visitas,
                'total'    => round((float) $c->total, 2),
                'medio'    => $c->visitas > 0 ? round((float) $c->total / $c->visitas, 2) : 0,
            ])->all();
    }

    // ------------------------------------------------------------------
    // Agenda
    // ------------------------------------------------------------------

    /**
     * Ocupación: minutos vendidos sobre minutos disponibles.
     *
     * No cuenta la pausa como ocupada: si lo hiciera, un salón lleno de
     * tintes parecería estar al 100% teniendo huecos vendibles dentro
     * de las esperas.
     */
    public function ocupacion(): array
    {
        $profesionales = Usuario::activos()->profesionales()->with('horarios')->get();
        $salida = [];

        foreach ($profesionales as $profesional) {
            $disponible = 0;
            $fecha = $this->desde->copy();

            while ($fecha->lte($this->hasta)) {
                foreach ($profesional->horarios->where('dia_semana', (int) $fecha->dayOfWeek) as $tramo) {
                    $disponible += Intervalo::desdeHoras($tramo->hora_ini, $tramo->hora_fin)->duracion();
                }

                $fecha->addDay();
            }

            $ocupado = (int) DB::table('reserva_lineas')
                ->join('reservas', 'reserva_lineas.reserva_id', '=', 'reservas.id')
                ->where('reserva_lineas.usuario_id', $profesional->id)
                ->whereBetween('reservas.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
                ->whereIn('reservas.estado', ['ATENDIDA', 'EN_CURSO', 'CONFIRMADA'])
                ->sum(DB::raw('reserva_lineas.duracion_min + reserva_lineas.tiempo_final_min'));

            $salida[] = [
                'etiqueta'   => $profesional->nombre,
                'color'      => $profesional->color_agenda,
                'disponible' => $disponible,
                'ocupado'    => $ocupado,
                'porcentaje' => $disponible > 0 ? round(($ocupado / $disponible) * 100, 1) : 0,
                'horas'      => round($ocupado / 60, 1),
            ];
        }

        usort($salida, fn ($a, $b) => $b['porcentaje'] <=> $a['porcentaje']);

        return $salida;
    }

    public function reservas(): array
    {
        $base = Reserva::whereBetween('fecha', [$this->desde->toDateString(), $this->hasta->toDateString()]);

        $porEstado = (clone $base)
            ->selectRaw('estado, COUNT(*) as cuantas')
            ->groupBy('estado')->pluck('cuantas', 'estado')->all();

        $porOrigen = (clone $base)
            ->selectRaw('origen, COUNT(*) as cuantas')
            ->groupBy('origen')->pluck('cuantas', 'origen')->all();

        $total  = array_sum($porEstado);
        $noShow = $porEstado['NO_SHOW'] ?? 0;
        $canceladas = $porEstado['CANCELADA'] ?? 0;

        return [
            'total'          => $total,
            'por_estado'     => $porEstado,
            'por_origen'     => $porOrigen,
            'online'         => $porOrigen['ONLINE'] ?? 0,
            'pct_online'     => $total > 0 ? round((($porOrigen['ONLINE'] ?? 0) / $total) * 100, 1) : 0,
            'no_shows'       => $noShow,
            'pct_no_show'    => $total > 0 ? round(($noShow / $total) * 100, 1) : 0,
            'canceladas'     => $canceladas,
            'pct_cancelada'  => $total > 0 ? round(($canceladas / $total) * 100, 1) : 0,
        ];
    }

    // ------------------------------------------------------------------
    // Control
    // ------------------------------------------------------------------

    /** Invitaciones: informe aparte, no son descuento comercial. */
    public function invitaciones(): array
    {
        return $this->lineas()
            ->where('ticket_lineas.es_invitacion', true)
            ->join('tickets', 'ticket_lineas.ticket_id', '=', 'tickets.id')
            ->leftJoin('usuarios', 'ticket_lineas.usuario_id', '=', 'usuarios.id')
            ->selectRaw('tickets.serie, tickets.numero, tickets.fecha,
                         ticket_lineas.descripcion, ticket_lineas.motivo_invitacion,
                         ticket_lineas.precio, usuarios.nombre as usuario')
            ->orderByDesc('tickets.fecha')
            ->get()
            ->map(fn ($i) => [
                'documento' => $i->serie . '-' . str_pad((string) $i->numero, 6, '0', STR_PAD_LEFT),
                'fecha'     => Carbon::parse($i->fecha)->format('d/m/Y H:i'),
                'etiqueta'  => $i->descripcion,
                'motivo'    => $i->motivo_invitacion,
                'usuario'   => $i->usuario,
                'total'     => round((float) $i->precio, 2),
            ])->all();
    }

    public function anulaciones(): array
    {
        return Ticket::whereBetween('fecha', [$this->desde, $this->hasta])
            ->where('estado', 'ANULADO')
            ->with(['usuario', 'anuladoPor' => fn ($q) => $q])
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (Ticket $t) => [
                'documento' => $t->referencia(),
                'fecha'     => $t->fecha->format('d/m/Y H:i'),
                'motivo'    => $t->motivo_anulacion,
                'usuario'   => $t->usuario?->nombre,
                'total'     => round((float) $t->total, 2),
            ])->all();
    }

    /** Base del futuro libro de facturas de VERI*FACTU. */
    public function libroFacturas(): array
    {
        return Ticket::whereBetween('fecha', [$this->desde, $this->hasta])
            ->whereIn('estado', ['COBRADO', 'ANULADO'])
            ->orderBy('serie')->orderBy('numero')
            ->get()
            ->map(fn (Ticket $t) => [
                'documento' => $t->referencia(),
                'fecha'     => $t->fecha->format('d/m/Y H:i'),
                'estado'    => $t->estado,
                'base'      => round((float) $t->base, 2),
                'impuesto'  => round((float) $t->impuesto, 2),
                'total'     => round((float) $t->total, 2),
                'hash'      => $t->verifactu_hash ? substr($t->verifactu_hash, 0, 12) . '…' : null,
            ])->all();
    }

    public function stock(): array
    {
        return Articulo::where('control_stock', true)
            ->with('familia')
            ->orderBy('stock')
            ->get()
            ->map(fn (Articulo $a) => [
                'etiqueta' => $a->nombre,
                'familia'  => $a->familia?->nombre,
                'stock'    => round((float) $a->stock, 3),
                'minimo'   => round((float) $a->stock_min, 3),
                'bajo'     => (float) $a->stock <= (float) $a->stock_min,
                'valor'    => round((float) $a->stock * (float) ($a->coste ?? $a->baseImponible()), 2),
            ])->all();
    }
}
