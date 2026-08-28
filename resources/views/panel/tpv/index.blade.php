@extends('panel.app')

@section('titulo', 'Punto de venta')

@section('contenido')

<div class="tpv" id="tpv"
     data-ticket="{{ $ticket->id }}"
     data-url-anadir="{{ route('panel.tpv.anadir', $ticket) }}"
     data-url-base="{{ url('panel/tpv/' . $ticket->id) }}"

     {{-- Que hacer tras cobrar, segun el ajuste del salon --}}
     data-tras-cobrar="{{ tenant('tras_cobrar') ?: 'NADA' }}"
     data-url-selector="{{ route('panel.selector') }}"
     data-url-inicio="{{ route('panel.inicio') }}">

    {{-- ================= Catálogo ================= --}}
    <section class="tpv__catalogo">
        <div class="tpv__pestanas">
            <button type="button" class="tpv__pestana tpv__pestana--activa" data-tipo="SERVICIO">Servicios</button>
            <button type="button" class="tpv__pestana" data-tipo="PRODUCTO">Productos</button>
            @if ($citasHoy->isNotEmpty())
                <button type="button" class="tpv__pestana" data-tipo="CITAS">
                    Citas de hoy <span class="tpv__badge">{{ $citasHoy->count() }}</span>
                </button>
            @endif
        </div>

        {{-- Familias --}}
        <div class="tpv__familias" id="familias">
            <button type="button" class="tpv__familia tpv__familia--activa" data-familia="">Todas</button>
            @foreach ($familias as $familia)
                <button type="button" class="tpv__familia"
                        data-familia="{{ $familia->id }}"
                        data-tipo="{{ $familia->tipo }}"
                        style="--color: {{ $familia->color }}">
                    {{ $familia->nombre }}
                </button>
            @endforeach
        </div>

        {{-- Artículos --}}
        <div class="tpv__rejilla" id="rejilla">
            @foreach ($familias as $familia)
                @foreach ($familia->articulos as $articulo)
                    <button type="button" class="articulo"
                            data-id="{{ $articulo->id }}"
                            data-familia="{{ $familia->id }}"
                            data-tipo="{{ $articulo->tipo }}"
                            style="--color: {{ $articulo->color ?? $familia->color }}">
                        @if ($url = $articulo->urlFoto())
                            <img src="{{ $url }}" alt="" class="articulo__foto" loading="lazy">
                        @endif
                        <span class="articulo__nombre">{{ $articulo->nombre }}</span>
                        <span class="articulo__precio">{{ number_format($articulo->precio, 2, ',', '.') }} €</span>
                        @if ($articulo->control_stock && $articulo->stock <= $articulo->stock_min)
                            <span class="articulo__stock">Stock {{ rtrim(rtrim(number_format($articulo->stock, 2, ',', '.'), '0'), ',') }}</span>
                        @endif
                    </button>
                @endforeach
            @endforeach
        </div>

        {{-- Citas de hoy --}}
        <div class="tpv__citas" id="citas" hidden>
            @foreach ($citasHoy as $cita)
                <a href="{{ route('panel.tpv', ['reserva' => $cita->id]) }}" class="cita-tpv">
                    <span class="cita-tpv__hora">{{ substr($cita->hora_ini, 0, 5) }}</span>
                    <span class="cita-tpv__datos">
                        <strong>{{ $cita->cliente_nombre }}</strong>
                        <small>{{ $cita->resumenServicios() }}</small>
                    </span>
                    <span class="cita-tpv__importe">{{ number_format($cita->importe_total, 2, ',', '.') }} €</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ================= Ticket ================= --}}
    <aside class="tpv__ticket">
        <div class="ticket-cab">
            <div>
                <strong id="ticketRef">{{ $ticket->referencia() }}</strong>
                <button type="button" class="ticket-cab__cliente" id="botonCliente">
                    <span id="ticketCliente">{{ $ticket->cliente?->nombreCompleto() ?? 'Sin cliente' }}</span>
                </button>
            </div>
            <a href="{{ route('panel.tpv.nuevo') }}" class="boton boton--secundario boton--pequeno">Nuevo</a>
        </div>

        {{-- Saldo, bonos y avisos del cliente --}}
        <div class="panel-cliente" id="panelCliente" hidden></div>

        @if ($usuario->en_formacion)
            <p class="ticket-formacion">
                DOCUMENTO DE FORMACIÓN · sin valor fiscal · solo efectivo
            </p>
        @endif

        <ul class="ticket-lineas" id="ticketLineas"></ul>

        <div class="ticket-totales">
            <div class="ticket-totales__fila">
                <span>Base</span><span id="ticketBase">0,00 €</span>
            </div>
            <div class="ticket-totales__fila">
                <span>{{ tenant('regimen_fiscal') === 'IVA' ? 'IVA' : 'IGIC' }}</span>
                <span id="ticketImpuesto">0,00 €</span>
            </div>
            <div class="ticket-totales__fila ticket-totales__fila--total">
                <span>TOTAL</span><span id="ticketTotal">0,00 €</span>
            </div>
            <div class="ticket-totales__fila" id="filaPendiente" hidden>
                <span>Pendiente</span><span id="ticketPendiente">0,00 €</span>
            </div>
        </div>

        <button type="button" class="boton boton--ancho boton--cobrar" id="abrirCobro" disabled>
            Cobrar
        </button>
    </aside>
</div>

{{-- ================= Buscador de cliente ================= --}}
<div class="modal-cobro" id="modalCliente" hidden>
    <div class="modal-cobro__caja modal-cobro__caja--ancha">
        <button type="button" class="modal-cobro__cerrar" id="cerrarCliente">&times;</button>

        <h3 class="modal-titulo">Cliente del ticket</h3>

        <div class="campo">
            <input type="text" id="buscarCliente" autocomplete="off"
                   data-teclado="texto"
                   placeholder="Nombre, teléfono o email">
            <p class="campo__pista">
                Busca por lo que recuerdes: los últimos dígitos del móvil suele
                ser lo más rápido.
            </p>
        </div>

        <ul class="clientes-resultados" id="resultadosCliente"></ul>

        {{-- Alta rápida --}}
        <div class="alta-rapida" id="bloqueAlta" hidden>
            <h4>Crear ficha nueva</h4>
            <p class="campo__pista">
                Con el nombre basta. El resto de datos se completan luego
                desde la ficha, sin la clienta esperando.
            </p>

            <div class="alta-rapida__campos">
                <input type="text" id="altaNombre" placeholder="Nombre *" data-teclado="texto">
                <input type="text" id="altaTelefono" placeholder="Teléfono" inputmode="tel">
            </div>

            <button type="button" class="boton boton--pequeno" id="confirmarAlta">
                Crear y asignar
            </button>
        </div>

        <button type="button" class="boton boton--secundario boton--ancho" id="quitarCliente"
                style="margin-top:1rem">
            Dejar el ticket sin cliente
        </button>
    </div>
</div>

{{-- ================= Modal de cobro ================= --}}
<div class="modal-cobro" id="modalCobro" hidden>
    <div class="modal-cobro__caja">
        <button type="button" class="modal-cobro__cerrar" id="cerrarCobro">&times;</button>

        <p class="modal-cobro__importe">
            <small>A cobrar</small>
            <strong id="cobroImporte">0,00 €</strong>
        </p>

        @if ($usuario->en_formacion)
            <p class="ticket-formacion">Solo efectivo</p>
        @endif

        <div class="medios">
            @foreach ($medios as $medio)
                <button type="button" class="medio" data-medio="{{ $medio }}">
                    {{ \App\Models\TicketCobro::MEDIOS[$medio] ?? $medio }}
                </button>
            @endforeach
        </div>

        <p class="aviso-saldo" id="avisoSaldo" hidden></p>

        {{-- Código del vale --}}
        <div id="bloqueVale" hidden>
            <label for="codigoVale">Código del vale</label>
            <input type="text" id="codigoVale" placeholder="V-XXXXXXXX"
                   data-teclado="texto" autocomplete="off"
                   style="text-transform:uppercase;font-family:monospace">
            <p class="campo__pista" style="margin-top:.4rem">
                Está impreso en el vale. Si sobra saldo, se queda en el vale
                para la próxima visita.
            </p>
        </div>

        {{-- Entregado, solo efectivo --}}
        <div id="bloqueEfectivo" hidden>
            <label for="entregado">Entregado</label>
            <input type="text" id="entregado" inputmode="decimal" placeholder="0,00">

            <div class="rapidos" id="rapidos"></div>

            <p class="cambio" id="cambio" hidden>
                Cambio: <strong id="cambioImporte">0,00 €</strong>
            </p>
        </div>

        <p class="modal-cobro__error" id="cobroError" hidden></p>

        <button type="button" class="boton boton--ancho boton--cobrar" id="confirmarCobro" disabled>
            Confirmar cobro
        </button>
    </div>
</div>

{{-- ================= Cobro terminado ================= --}}
<div class="modal-cobro" id="modalHecho" hidden>
    <div class="modal-cobro__caja modal-cobro__caja--ok">
        <p class="hecho-icono">✓</p>
        <p class="hecho-titulo" id="hechoTitulo">Cobrado</p>
        <p class="hecho-cambio" id="hechoCambio" hidden></p>
        {{--
            Solo aparece cuando el terminal esta en modo PREGUNTAR.
            El JavaScript lo muestra u oculta segun la respuesta del cobro.
        --}}
        <button type="button" class="boton boton--secundario boton--ancho"
                id="hechoImprimir" hidden style="margin-bottom:.6rem">
            Imprimir ticket
        </button>

        <button type="button" class="boton boton--ancho" id="hechoNuevo">Nueva venta</button>
    </div>
</div>

@php
    // Los datos del TPV se montan aqui y no dentro de @json(...).
    // Blade se atraganta con cierres «fn () => [...]» dentro de una
    // directiva: cuenta parentesis para delimitar el argumento y los
    // corchetes anidados le hacen cortar la expresion a destiempo.
    $datosTpv = [
        'formacion'    => (bool) $usuario->en_formacion,
        'puedeQuitar'  => $usuario->tienePermiso(App\Support\Permisos::TPV_ANULAR_LINEA),
        'puedeDto'     => $usuario->tienePermiso(App\Support\Permisos::TPV_DESCUENTO),
        'puedeInvitar' => $usuario->tienePermiso(App\Support\Permisos::TPV_INVITACION),
        'csrf'         => csrf_token(),
    ];

    $lineasTpv = [];

    foreach ($ticket->lineas as $l) {
        $lineasTpv[] = [
            'id'          => $l->id,
            'descripcion' => $l->descripcion,
            'cantidad'    => (float) $l->cantidad,
            'precio'      => (float) $l->precio,
            'dto'         => (float) $l->dto_pct,
            'importe'     => (float) $l->importe,
            'invitacion'  => (bool) $l->es_invitacion,
            'bono'        => $l->bono_id ? $l->bono?->codigo : null,
        ];
    }

    $cobrosTpv = [];

    foreach ($ticket->cobros as $c) {
        $cobrosTpv[] = [
            'medio'   => $c->nombreMedio(),
            'importe' => (float) $c->importe,
        ];
    }

    $clienteTpv = $ticket->cliente;

    $datosTicket = [
        'id'         => $ticket->id,
        'referencia' => $ticket->referencia(),
        'estado'     => $ticket->estado,
        'base'       => (float) $ticket->base,
        'impuesto'   => (float) $ticket->impuesto,
        'total'      => (float) $ticket->total,
        'pendiente'  => $ticket->pendiente(),
        'cliente_id' => $ticket->cliente_id,

        // Saldo del monedero, para ofrecerlo al cobrar en vez de
        // cobrarle otra vez algo que ya tiene pagado
        'saldo'      => $clienteTpv ? round((float) $clienteTpv->saldo_monedero, 2) : 0,
        'tiene_bonos'=> (bool) $clienteTpv?->bonosActivos()->exists(),
        'lineas'     => $lineasTpv,
        'cobros'     => $cobrosTpv,
    ];
@endphp

@push('scripts')
<script src="{{ asset('js/tpv.js') }}?v=28"></script>
<script>
    window.TPV_DATOS  = {!! json_encode($datosTpv, JSON_UNESCAPED_UNICODE) !!};
    window.TPV_TICKET = {!! json_encode($datosTicket, JSON_UNESCAPED_UNICODE) !!};
    iniciarTpv();
</script>
@endpush

@endsection
