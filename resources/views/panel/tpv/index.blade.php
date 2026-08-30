@extends('panel.app')

@section('titulo', 'Punto de venta')

@section('contenido')

<div class="tpv" id="tpv"
     data-ticket="{{ $ticket->id }}"
     data-url-anadir="{{ route('panel.tpv.anadir', $ticket) }}"
     data-url-base="{{ url('panel/tpv/' . $ticket->id) }}"

     {{-- Que hacer tras cobrar, segun el ajuste del salon --}}
     data-tras-cobrar="{{ tenant('tras_cobrar') ?: 'NADA' }}"
     {{--
         El «destino» hace que, tras meter el PIN, se vuelva al TPV y no
         al Inicio. En un mostrador con cola, ahorra dos toques por venta.
     --}}
     data-url-selector="{{ route('panel.selector', ['destino' => 'tpv']) }}"
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
            <button type="button" class="tpv__familia tpv__familia--activa" data-familia="">
                <span class="tpv__familia-nombre">Todas</span>
            </button>
            {{--
                La familia con imagen se pinta como tarjeta, con la foto
                de fondo y el nombre debajo. La que no tiene sigue siendo
                la pastilla de siempre: quien no suba fotos no nota
                ningun cambio.

                La clase base se mantiene en los dos casos porque tpv.js
                busca .tpv__familia para filtrar y para marcar la activa.
            --}}
            @foreach ($familias as $familia)
                @php
                    $foto = $familia->urlImagen();
                @endphp

                <button type="button"
                        @class(['tpv__familia', 'tpv__familia--foto' => $foto])
                        data-familia="{{ $familia->id }}"
                        data-tipo="{{ $familia->tipo }}"
                        style="--color: {{ $familia->color }}">
                    @if ($foto)
                        <img src="{{ $foto }}" alt="" class="tpv__familia-foto">
                    @endif
                    <span class="tpv__familia-nombre">{{ $familia->nombre }}</span>
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

    {{--
        ================= Lateral =================

        El ticket y las acciones de caja comparten la columna derecha.
        Se envuelven juntos porque .tpv es una rejilla de dos columnas:
        sin este contenedor, la barra caeria en una fila nueva, por
        debajo del catalogo, y no bajo el panel de cobro.
    --}}
    <div class="tpv__lateral">

    {{-- ---- Ticket ---- --}}
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

{{--
    ---- Acciones de caja ----

    Debajo del panel de cobro, ya fuera del aside.

    Fuera y no dentro a proposito: .tpv__ticket es flex con
    max-height: calc(100vh - 110px), asi que dentro los botones
    competian por el alto con la lista de lineas y quedaban
    recortados. Como hermanos del aside, cada uno tiene el suyo.

    Los permisos son los que ya existian; no hay ninguno nuevo.
--}}
@php
    $puedeCajon  = $usuario->tienePermiso(App\Support\Permisos::TPV_ABRIR_CAJON);
    $puedeX      = $usuario->tienePermiso(App\Support\Permisos::CAJA_ENTRADAS_SALIDAS);
    $puedeCerrar = $usuario->tienePermiso(App\Support\Permisos::CAJA_CIERRE);
@endphp

@if ($puedeCajon || $puedeX || $puedeCerrar)
    <div class="tpv-caja" id="tpvCaja"
         data-url-cajon="{{ $puedeCajon ? route('panel.tpv.cajon') : '' }}"
         data-url-x="{{ $puedeX ? route('panel.tpv.informe-x') : '' }}"
         data-url-x-imprimir="{{ $puedeX ? route('panel.tpv.informe-x.imprimir') : '' }}"
         data-url-cierre="{{ $puedeCerrar ? route('panel.tpv.cierre') : '' }}"
         data-url-cierre-hacer="{{ $puedeCerrar ? route('panel.tpv.cierre.hacer') : '' }}"
         data-url-reauth="{{ route('panel.reautenticar') }}">

        @if ($puedeCajon)
            <button type="button" class="tpv-caja__boton" id="accionCajon"
                    title="Abrir el cajón portamonedas">
                <img src="{{ asset('img/tpv/abrir_cajon.png') }}?v=34" alt="">
                <span>Cajón</span>
            </button>
        @endif

        @if ($puedeX)
            <button type="button" class="tpv-caja__boton" id="accionInformeX"
                    title="Cómo va la jornada, sin cerrar nada">
                <img src="{{ asset('img/tpv/informe_x.png') }}?v=34" alt="">
                <span>Informe X</span>
            </button>
        @endif

        @if ($puedeCerrar)
            <button type="button" class="tpv-caja__boton" id="accionCierre"
                    title="Cerrar la jornada">
                <img src="{{ asset('img/tpv/informe_z.png') }}?v=34" alt="">
                <span>Cerrar día</span>
            </button>
        @endif
    </div>

    <p class="tpv-caja__aviso" id="tpvCajaAviso" hidden></p>
@endif

    </div>{{-- /.tpv__lateral --}}
</div>{{-- /.tpv --}}

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

{{-- ================= Apertura de jornada ================= --}}
@if ($sinAbrir ?? false)
<div class="modal-cobro" id="modalJornada">
    <div class="modal-cobro__caja">
        <h3 class="modal-titulo">Empezar la jornada</h3>

        <p class="informe-caja__pie">
            No hay ninguna jornada abierta. Cuenta el efectivo que hay en el
            cajón e introdúcelo: es el fondo con el que arranca la caja.
        </p>

        <form method="POST" action="{{ route('panel.tpv.jornada') }}">
            @csrf

            <label class="informe-caja__etiqueta" for="fondoInicial">
                Fondo de caja
            </label>
            <input type="number" step="0.01" min="0" name="fondo" id="fondoInicial"
                   class="informe-caja__importe" placeholder="0,00" autofocus>

            <button type="submit" class="boton boton--ancho boton--cobrar"
                    style="margin-top:1rem">
                Empezar la jornada
            </button>
        </form>

        {{--
            Se puede seguir sin abrir.

            Bloquear el TPV porque nadie ha contado el cajon seria peor
            que el problema: con fondo cero el arqueo saldra descuadrado
            al cerrar, y eso ya avisa por si solo.
        --}}
        <button type="button" class="boton boton--secundario boton--ancho"
                style="margin-top:.6rem"
                onclick="document.getElementById('modalJornada').hidden = true">
            Ahora no
        </button>
    </div>
</div>
@endif

{{-- ================= Informe X ================= --}}
@if ($puedeX ?? false)
<div class="modal-cobro" id="modalInformeX" hidden>
    <div class="modal-cobro__caja modal-cobro__caja--ancha">
        <button type="button" class="modal-cobro__cerrar" data-cerrar-caja>&times;</button>

        <h3 class="modal-titulo">Informe X</h3>
        <p class="informe-caja__pie">
            Cómo va la jornada ahora mismo. No cierra nada: se puede
            mirar las veces que haga falta.
        </p>

        <div id="cuerpoInformeX" class="informe-caja">
            <p class="informe-caja__cargando">Calculando…</p>
        </div>

        <button type="button" class="boton boton--ancho" id="imprimirInformeX">
            Imprimir informe X
        </button>
    </div>
</div>
@endif

{{-- ================= Cierre de jornada ================= --}}
@if ($puedeCerrar ?? false)
<div class="modal-cobro" id="modalCierre" hidden>
    <div class="modal-cobro__caja modal-cobro__caja--ancha">
        <button type="button" class="modal-cobro__cerrar" data-cerrar-caja>&times;</button>

        <h3 class="modal-titulo">Cerrar la jornada</h3>

        <div id="cuerpoCierre" class="informe-caja">
            <p class="informe-caja__cargando">Cargando…</p>
        </div>

        <div id="formCierre" hidden>
            <label class="informe-caja__etiqueta" for="efectivoContado">
                Efectivo contado en el cajón
            </label>
            <input type="number" step="0.01" min="0" id="efectivoContado"
                   class="informe-caja__importe" placeholder="0,00">

            {{--
                El descuadre se enseña ANTES de confirmar.
                Enterarse despues, con la jornada ya cerrada e
                inmutable, no sirve de nada.
            --}}
            <p class="informe-caja__descuadre" id="avisoDescuadre" hidden></p>

            <label class="informe-caja__etiqueta" for="observacionesCierre">
                Observaciones (opcional)
            </label>
            <input type="text" id="observacionesCierre"
                   class="informe-caja__texto" maxlength="1000"
                   placeholder="Falta de cambio, incidencia…">

            <button type="button" class="boton boton--ancho boton--cobrar" id="confirmarCierre">
                Cerrar la jornada
            </button>

            <p class="informe-caja__pie">
                Se imprimen el cierre y el parte de trabajo. Los tickets
                cerrados ya no se podrán anular.
            </p>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script src="{{ asset('js/tpv.js') }}?v=28"></script>
<script>
    window.TPV_DATOS  = {!! json_encode($datosTpv, JSON_UNESCAPED_UNICODE) !!};
    window.TPV_TICKET = {!! json_encode($datosTicket, JSON_UNESCAPED_UNICODE) !!};
    iniciarTpv();
</script>

{{--
    Cajon, informe X y cierre.

    Bloque cerrado, sin nada compartido con la venta: se puede tocar sin
    riesgo de romper el cobro.
--}}
<script>
(function () {
    'use strict';

    var barra = document.getElementById('tpvCaja');

    if (!barra) {
        return;
    }

    var aviso   = document.getElementById('tpvCajaAviso');
    var meta    = document.querySelector('meta[name="csrf-token"]');
    var testigo = meta ? meta.getAttribute('content') : '';

    var urls = {
        cajon:       barra.dataset.urlCajon,
        x:           barra.dataset.urlX,
        xImprimir:   barra.dataset.urlXImprimir,
        cierre:      barra.dataset.urlCierre,
        cerrar:      barra.dataset.urlCierreHacer,
        reauth:      barra.dataset.urlReauth
    };

    var teorico = 0;

    /* Que se estaba haciendo cuando el servidor pidio la contrasena */
    var accionEnCurso = '';

    // ---- Utilidades ------------------------------------------------

    function euros(valor) {
        return (Number(valor) || 0).toFixed(2).replace('.', ',') + ' \u20ac';
    }

    function escapar(texto) {
        var caja = document.createElement('span');
        caja.textContent = (texto === null || texto === undefined) ? '' : texto;

        return caja.innerHTML;
    }

    var reloj = null;

    function mensaje(texto, esError) {
        if (!aviso) {
            return;
        }

        aviso.textContent = texto;
        aviso.classList.toggle('tpv-caja__aviso--error', !!esError);
        aviso.hidden = false;

        clearTimeout(reloj);
        reloj = setTimeout(function () { aviso.hidden = true; }, 6000);
    }

    /**
     * Los fallos se cuentan siempre.
     *
     * Un boton que no hace nada y no dice por que es peor que no tener
     * boton: en el mostrador lo pulsan cinco veces mas y acaban con
     * cinco trabajos en la cola.
     */
    function pedir(url, metodo, cuerpo, accion) {
        accionEnCurso = accion || accionEnCurso;

        var opciones = {
            method: metodo || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if ((metodo || 'GET') !== 'GET') {
            opciones.headers['X-CSRF-TOKEN'] = testigo;

            if (cuerpo) {
                opciones.headers['Content-Type'] = 'application/json';
                opciones.body = JSON.stringify(cuerpo);
            }
        }

        return fetch(url, opciones).then(function (respuesta) {
            return respuesta.json().catch(function () { return {}; })
                .then(function (datos) {
                    if (respuesta.status === 423) {
                        /*
                         * Permiso sensible: toca confirmar contrasena.
                         *
                         * Se manda el destino para volver AQUI, y con
                         * que accion estaba pendiente, para reabrirla
                         * sola. Sin esto se acababa en el Inicio y
                         * habia que rehacer todo el camino.
                         */
                        window.location.href = urls.reauth + '?destino='
                            + encodeURIComponent(window.location.pathname + '?accion=' + accionEnCurso);

                        return Promise.reject(new Error('reauth'));
                    }

                    if (!respuesta.ok || datos.ok === false) {
                        throw new Error(datos.mensaje || 'No se ha podido completar la accion.');
                    }

                    return datos;
                });
        });
    }

    function abrir(id) {
        var modal = document.getElementById(id);

        if (modal) {
            modal.hidden = false;
        }
    }

    function cerrarModal(modal) {
        if (modal) {
            modal.hidden = true;
        }
    }

    Array.prototype.forEach.call(
        document.querySelectorAll('[data-cerrar-caja]'),
        function (boton) {
            boton.addEventListener('click', function () {
                cerrarModal(boton.closest('.modal-cobro'));
            });
        }
    );

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            cerrarModal(document.getElementById('modalInformeX'));
            cerrarModal(document.getElementById('modalCierre'));
        }
    });

    function fila(etiqueta, valor, fuerte) {
        return '<div class="informe-caja__fila' + (fuerte ? ' informe-caja__fila--fuerte' : '') + '">'
             + '<span>' + escapar(etiqueta) + '</span>'
             + '<span>' + escapar(valor) + '</span>'
             + '</div>';
    }

    /** El cuerpo del arqueo, comun al informe X y al cierre. */
    function pintarArqueo(informe, conAviso) {
        var p = [];

        p.push('<div class="informe-caja__bloque">');
        p.push(fila('Desde', informe.desde));
        p.push(fila('Tickets', String(informe.tickets)));
        p.push('</div>');

        p.push('<div class="informe-caja__bloque">');
        p.push(fila('Base imponible', euros(informe.base)));
        p.push(fila(informe.etiqueta_impuesto, euros(informe.impuesto)));
        p.push(fila('Ventas', euros(informe.ventas), true));
        p.push(fila('Ticket medio', euros(informe.ticket_medio)));
        p.push('</div>');

        if (informe.medios.length) {
            p.push('<div class="informe-caja__bloque"><h4>Por medio de pago</h4>');

            informe.medios.forEach(function (m) {
                p.push(fila(m.nombre, euros(m.importe)));
            });

            p.push('</div>');
        }

        p.push('<div class="informe-caja__bloque"><h4>Efectivo en caja</h4>');
        p.push(fila('Fondo inicial', euros(informe.efectivo_inicial)));
        p.push(fila('Ventas en efectivo', euros(informe.efectivo_ventas)));

        if (informe.entradas > 0) {
            p.push(fila('Entradas', euros(informe.entradas)));
        }

        if (informe.salidas > 0) {
            p.push(fila('Salidas', '-' + euros(informe.salidas)));
        }

        p.push(fila('Debe haber', euros(informe.efectivo_teorico), true));
        p.push('</div>');

        if (informe.por_profesional.length) {
            p.push('<div class="informe-caja__bloque"><h4>Por profesional</h4>');

            informe.por_profesional.forEach(function (linea) {
                p.push(fila(linea.nombre, euros(linea.importe)));
            });

            p.push('</div>');
        }

        if (informe.formacion > 0) {
            p.push('<p class="informe-caja__nota">' + informe.formacion
                + ' documento(s) de formacion quedan fuera de este informe.</p>');
        }

        if (conAviso) {
            p.push('<p class="informe-caja__nota">'
                + 'Esto es una lectura: no cierra la jornada ni marca los tickets.'
                + '</p>');
        }

        return p.join('');
    }

    // ---- Cajon -----------------------------------------------------

    var btnCajon = document.getElementById('accionCajon');

    if (btnCajon && urls.cajon) {
        btnCajon.addEventListener('click', function () {
            btnCajon.disabled = true;

            pedir(urls.cajon, 'POST')
                .then(function (d) { mensaje(d.mensaje || 'Cajon abierto.', false); })
                .catch(function (e) {
                    if (e.message !== 'reauth') { mensaje(e.message, true); }
                })
                .finally(function () {
                    // Espera corta: evita la ristra de pulsaciones
                    setTimeout(function () { btnCajon.disabled = false; }, 1200);
                });
        });
    }

    // ---- Informe X -------------------------------------------------

    var btnX = document.getElementById('accionInformeX');

    if (btnX && urls.x) {
        btnX.addEventListener('click', function () {
            document.getElementById('cuerpoInformeX').innerHTML =
                '<p class="informe-caja__cargando">Calculando\u2026</p>';

            abrir('modalInformeX');

            pedir(urls.x, 'GET', null, 'informe-x')
                .then(function (d) {
                    document.getElementById('cuerpoInformeX').innerHTML =
                        pintarArqueo(d.informe, true);
                })
                .catch(function (e) {
                    if (e.message === 'reauth') { return; }

                    document.getElementById('cuerpoInformeX').innerHTML =
                        '<p class="informe-caja__nota">' + escapar(e.message) + '</p>';
                });
        });
    }

    var btnImprimirX = document.getElementById('imprimirInformeX');

    if (btnImprimirX && urls.xImprimir) {
        btnImprimirX.addEventListener('click', function () {
            btnImprimirX.disabled = true;

            pedir(urls.xImprimir, 'POST')
                .then(function (d) {
                    cerrarModal(document.getElementById('modalInformeX'));
                    mensaje(d.mensaje || 'Informe X enviado a la impresora.', false);
                })
                .catch(function (e) {
                    if (e.message !== 'reauth') { mensaje(e.message, true); }
                })
                .finally(function () { btnImprimirX.disabled = false; });
        });
    }

    // ---- Cierre ----------------------------------------------------

    var btnCierre = document.getElementById('accionCierre');

    if (btnCierre && urls.cierre) {
        btnCierre.addEventListener('click', function () {
            document.getElementById('cuerpoCierre').innerHTML =
                '<p class="informe-caja__cargando">Cargando\u2026</p>';
            document.getElementById('formCierre').hidden = true;

            abrir('modalCierre');

            pedir(urls.cierre, 'GET', null, 'cierre')
                .then(function (d) {
                    document.getElementById('cuerpoCierre').innerHTML =
                        pintarArqueo(d.informe, false);

                    teorico = d.informe.efectivo_teorico;

                    if (d.hay_algo) {
                        document.getElementById('formCierre').hidden = false;
                        document.getElementById('efectivoContado').value = '';
                        document.getElementById('observacionesCierre').value = '';
                        document.getElementById('avisoDescuadre').hidden = true;
                    } else {
                        document.getElementById('cuerpoCierre').innerHTML +=
                            '<p class="informe-caja__nota">No hay nada que cerrar.</p>';
                    }
                })
                .catch(function (e) {
                    if (e.message === 'reauth') { return; }

                    document.getElementById('cuerpoCierre').innerHTML =
                        '<p class="informe-caja__nota">' + escapar(e.message) + '</p>';
                });
        });
    }

    var campoContado = document.getElementById('efectivoContado');

    if (campoContado) {
        campoContado.addEventListener('input', function () {
            var caja = document.getElementById('avisoDescuadre');

            if (campoContado.value === '') {
                caja.hidden = true;

                return;
            }

            var diferencia = Math.round((parseFloat(campoContado.value) - teorico) * 100) / 100;

            caja.hidden = false;
            caja.classList.toggle('informe-caja__descuadre--mal', Math.abs(diferencia) >= 0.01);

            if (Math.abs(diferencia) < 0.01) {
                caja.textContent = 'La caja cuadra.';
            } else if (diferencia > 0) {
                caja.textContent = 'Sobran ' + euros(diferencia) + '.';
            } else {
                caja.textContent = 'Faltan ' + euros(Math.abs(diferencia)) + '.';
            }
        });
    }

    var btnConfirmar = document.getElementById('confirmarCierre');

    if (btnConfirmar && urls.cerrar) {
        btnConfirmar.addEventListener('click', function () {
            var contado = document.getElementById('efectivoContado').value;

            if (contado === '') {
                mensaje('Cuenta el efectivo del cajon antes de cerrar.', true);

                return;
            }

            /**
             * Confirmacion explicita.
             *
             * El cierre marca los tickets y ya no se pueden anular. No
             * es una accion que deba salir de un solo toque.
             */
            if (!window.confirm('Se va a cerrar la jornada. Los tickets cerrados ya no '
                    + 'se podran anular. \u00bfContinuar?')) {
                return;
            }

            btnConfirmar.disabled = true;

            pedir(urls.cerrar, 'POST', {
                efectivo_contado: contado,
                observaciones: document.getElementById('observacionesCierre').value || null
            })
                .then(function (d) {
                    // Al detalle del cierre, que es donde se reimprime
                    window.location.href = d.url;
                })
                .catch(function (e) {
                    if (e.message !== 'reauth') { mensaje(e.message, true); }

                    btnConfirmar.disabled = false;
                });
        });
    }
    /*
     * Vuelta desde la pantalla de contrasena.
     *
     * Si la direccion trae ?accion=..., se pulsa ese boton solo, para
     * que el usuario siga donde lo dejo en vez de tener que buscarlo.
     */
    (function () {
        var accion = new URLSearchParams(window.location.search).get('accion');

        var botones = {
            'cierre':    btnCierre,
            'informe-x': btnX
        };

        if (accion && botones[accion]) {
            botones[accion].click();

            // Se limpia la direccion: al recargar no debe repetirse
            window.history.replaceState({}, '', window.location.pathname);
        }
    })();
})();
</script>
@endpush

@endsection
