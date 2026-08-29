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

{{--
    ================= Acciones de caja =================

    Cajon, X y Z: barra fija abajo a la derecha de la pantalla, fuera
    del panel del ticket y fuera del modal de cobro.

    VA AQUI Y NO DENTRO DEL ASIDE A PROPOSITO. Dentro, el panel del
    ticket la recortaba y no se veia. Como elemento fijo no depende del
    alto ni del overflow de nadie.

    Para que no tape el boton de cobrar se le reserva sitio al final del
    panel del ticket con padding-bottom, mas abajo en el CSS.
--}}
@php
    $usuarioTpv    = $usuario;
    $puedeCajon    = $usuarioTpv->tienePermiso(App\Support\Permisos::TPV_ABRIR_CAJON);
    $puedeInformes = $usuarioTpv->tienePermiso(App\Support\Permisos::TPV_INFORMES_CAJA);
@endphp

@if ($puedeCajon || $puedeInformes)
    <div class="tpv-acciones" id="tpvAcciones"
         data-url-cajon="{{ $puedeCajon ? route('panel.tpv.cajon') : '' }}"
         data-url-x="{{ $puedeInformes ? route('panel.tpv.informe-x') : '' }}"
         data-url-x-imprimir="{{ $puedeInformes ? route('panel.tpv.informe-x.imprimir') : '' }}"
         data-url-z="{{ $puedeInformes ? route('panel.tpv.informe-z') : '' }}"
         data-url-z-imprimir="{{ $puedeInformes ? route('panel.tpv.informe-z.imprimir', ['cierre' => '__ID__']) : '' }}">

        @if ($puedeCajon)
            <button type="button" class="tpv-accion" id="accionCajon"
                    title="Abrir el cajón portamonedas">
                <img src="{{ asset('img/tpv/abrir_cajon.png') }}" alt="">
                <span>Cajón</span>
            </button>
        @endif

        @if ($puedeInformes)
            <button type="button" class="tpv-accion" id="accionInformeX"
                    title="Lectura de la jornada, sin cerrar">
                <img src="{{ asset('img/tpv/informe_x.png') }}" alt="">
                <span>Informe X</span>
            </button>

            <button type="button" class="tpv-accion" id="accionInformeZ"
                    title="Cierre de jornada">
                <img src="{{ asset('img/tpv/informe_z.png') }}" alt="">
                <span>Informe Z</span>
            </button>
        @endif
    </div>

    <p class="tpv-acciones__aviso" id="tpvAccionesAviso" hidden></p>
@endif

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

{{-- ================= Informe X ================= --}}
@if ($puedeInformes ?? false)
<div class="modal-cobro" id="modalInformeX" hidden>
    <div class="modal-cobro__caja modal-cobro__caja--ancha">
        <button type="button" class="modal-cobro__cerrar" data-cerrar-informe>&times;</button>

        <h3 class="modal-titulo">Informe X · lectura de jornada</h3>
        <p class="campo__pista">
            Enseña cómo va la caja ahora mismo. No cierra nada: se puede
            mirar las veces que haga falta.
        </p>

        <div id="cuerpoInformeX" class="informe-x">
            <p class="informe-x__cargando">Calculando…</p>
        </div>

        <button type="button" class="boton boton--ancho" id="imprimirInformeX">
            Imprimir informe X
        </button>
    </div>
</div>

{{-- ================= Informe Z ================= --}}
<div class="modal-cobro" id="modalInformeZ" hidden>
    <div class="modal-cobro__caja modal-cobro__caja--ancha">
        <button type="button" class="modal-cobro__cerrar" data-cerrar-informe>&times;</button>

        <h3 class="modal-titulo">Informe Z · cierre de jornada</h3>

        <div id="cuerpoInformeZ" class="informe-x">
            <p class="informe-x__cargando">Cargando…</p>
        </div>
    </div>
</div>

<style>
/*
 * Acciones de caja del TPV.
 *
 * Va aqui y no en tpv.css para que el bloque entero (marcado, estilo y
 * comportamiento) viaje en un solo fichero mientras se prueba. Cuando
 * este asentado, se puede mover tal cual a public/css/tpv.css.
 */
.tpv-acciones {
    /*
     * Fija a la pantalla, no al panel del ticket.
     *
     * .tpv__ticket es flex con max-height: calc(100vh - 110px), asi que
     * dentro de el la barra competia por el alto con las lineas del
     * ticket. Fija no depende del alto de nadie.
     *
     * z-index 60: por debajo de .modal-cobro (70) y de .tpv-nota (90),
     * para que el cobro y los avisos sigan tapandola.
     */
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 60;

    display: flex;
    gap: .5rem;
    padding: .5rem;
    border: 1px solid var(--borde);
    border-radius: 14px;
    background: var(--panel);
    box-shadow: 0 4px 20px rgba(0, 0, 0, .45);
}

/* Sitio para que la barra no tape el boton de cobrar */
.tpv__ticket { padding-bottom: 6.5rem; }

.tpv-accion {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    width: 5.2rem;
    min-height: 5rem;
    padding: .55rem .3rem;
    background: var(--panel2);
    border: 1px solid var(--borde);
    border-radius: 11px;
    color: var(--suave);
    font-size: .72rem;
    font-weight: 600;
    line-height: 1.15;
    text-align: center;
    cursor: pointer;
    transition: transform .1s, filter .1s;
}

.tpv-accion:hover { color: var(--texto); border-color: var(--marca); }
.tpv-accion:active { transform: scale(.94); }
.tpv-accion:disabled { opacity: .4; cursor: not-allowed; }

/*
 * Los iconos son trazo blanco sobre transparente, asi que heredan el
 * color del boton. Sin esto no se distinguirian del panel oscuro.
 */
.tpv-accion img {
    width: 1.9rem;
    height: 1.9rem;
    object-fit: contain;
    pointer-events: none;
    opacity: .85;
}

.tpv-accion:hover img { opacity: 1; }

/* En pantalla estrecha la barra se estira de lado a lado */
@media (max-width: 640px) {
    .tpv-acciones { left: 1rem; right: 1rem; justify-content: space-around; }
    .tpv-accion { flex: 1; width: auto; }
}

.tpv-acciones__aviso {
    position: fixed;
    right: 1rem;
    bottom: 7.6rem;
    z-index: 60;
    max-width: 22rem;
    margin: 0;
    padding: .6rem .85rem;
    border: 1px solid var(--borde);
    border-radius: 10px;
    background: var(--panel);
    color: var(--texto);
    font-size: .84rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .45);
}

.tpv-acciones__aviso--error { border-color: var(--error); color: #fca5a5; }

/* ---- Cuerpo de los informes en pantalla ---- */
.informe-x { margin: .8rem 0 1rem; }
.informe-x__cargando { text-align: center; opacity: .6; }

.informe-x__bloque + .informe-x__bloque {
    margin-top: .9rem;
    padding-top: .7rem;
    border-top: 1px solid var(--borde);
}

.informe-x__bloque h4 {
    margin: 0 0 .4rem;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--suave);
    opacity: 1;
}

.informe-x__fila {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .18rem 0;
    font-variant-numeric: tabular-nums;
}

.informe-x__fila--fuerte { font-weight: 700; font-size: 1.05rem; }

.informe-x__nota {
    margin-top: .9rem;
    padding: .5rem .7rem;
    border-radius: 8px;
    background: var(--panel2);
    border: 1px solid var(--borde);
    color: var(--suave);
    font-size: .8rem;
}

.informe-x__acciones {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-top: 1rem;
}

.informe-x__acciones .boton { flex: 1 1 10rem; }
</style>
@endif

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

{{--
    Acciones de caja: cajon, informe X e informe Z.

    Va aparte de tpv.js a proposito. Es un bloque cerrado, sin nada
    compartido con la venta, y asi se puede tocar sin riesgo de romper
    el cobro. Si mas adelante crece, se mueve entero a su propio fichero.
--}}
<script>
(function () {
    'use strict';

    var barra = document.getElementById('tpvAcciones');

    if (!barra) {
        return;
    }

    var aviso  = document.getElementById('tpvAccionesAviso');
    var csrf   = document.querySelector('meta[name="csrf-token"]');
    var testigo = csrf ? csrf.getAttribute('content') : '';

    var urlCajon     = barra.dataset.urlCajon;
    var urlX         = barra.dataset.urlX;
    var urlXImprimir = barra.dataset.urlXImprimir;
    var urlZ         = barra.dataset.urlZ;
    var urlZImprimir = barra.dataset.urlZImprimir;

    // ----------------------------------------------------------------

    function euros(valor) {
        return (Number(valor) || 0).toFixed(2).replace('.', ',') + ' €';
    }

    function escapar(texto) {
        var caja = document.createElement('span');
        caja.textContent = texto === null || texto === undefined ? '' : texto;

        return caja.innerHTML;
    }

    var temporizador = null;

    function mensaje(texto, esError) {
        if (!aviso) {
            return;
        }

        aviso.textContent = texto;
        aviso.classList.toggle('tpv-acciones__aviso--error', !!esError);
        aviso.hidden = false;

        clearTimeout(temporizador);
        temporizador = setTimeout(function () { aviso.hidden = true; }, 6000);
    }

    /**
     * Llamada al servidor.
     *
     * Los fallos se cuentan siempre. Un boton que no hace nada y no dice
     * por que es peor que no tener boton: quien esta en el mostrador lo
     * pulsa cinco veces mas y acaba con cinco trabajos en la cola.
     */
    function pedir(url, metodo) {
        var opciones = {
            method: metodo || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if ((metodo || 'GET') !== 'GET') {
            opciones.headers['X-CSRF-TOKEN'] = testigo;
        }

        return fetch(url, opciones).then(function (respuesta) {
            return respuesta.json()
                .catch(function () { return {}; })
                .then(function (datos) {
                    if (respuesta.status === 423) {
                        // Permiso sensible: hay que confirmar contrasena
                        window.location.href = '{{ route('panel.reautenticar') }}';

                        return Promise.reject(new Error('reauth'));
                    }

                    if (!respuesta.ok || datos.ok === false) {
                        throw new Error(datos.mensaje || 'No se ha podido completar la acción.');
                    }

                    return datos;
                });
        });
    }

    // ---- Modales ---------------------------------------------------

    function abrir(id) {
        var modal = document.getElementById(id);

        if (modal) {
            modal.hidden = false;
        }
    }

    function cerrar(modal) {
        if (modal) {
            modal.hidden = true;
        }
    }

    Array.prototype.forEach.call(
        document.querySelectorAll('[data-cerrar-informe]'),
        function (boton) {
            boton.addEventListener('click', function () {
                cerrar(boton.closest('.modal-cobro'));
            });
        }
    );

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') {
            return;
        }

        cerrar(document.getElementById('modalInformeX'));
        cerrar(document.getElementById('modalInformeZ'));
    });

    // ---- Cajon -----------------------------------------------------

    var botonCajon = document.getElementById('accionCajon');

    if (botonCajon && urlCajon) {
        botonCajon.addEventListener('click', function () {
            botonCajon.disabled = true;

            pedir(urlCajon, 'POST')
                .then(function (datos) {
                    mensaje(datos.mensaje || 'Cajón abierto.', false);
                })
                .catch(function (error) {
                    if (error.message !== 'reauth') {
                        mensaje(error.message, true);
                    }
                })
                .finally(function () {
                    // Pequena espera: evita la ristra de pulsaciones
                    setTimeout(function () { botonCajon.disabled = false; }, 1200);
                });
        });
    }

    // ---- Informe X -------------------------------------------------

    function pintarX(informe) {
        var partes = [];

        partes.push('<div class="informe-x__bloque">');
        partes.push(fila('Desde', informe.desde));
        partes.push(fila('Emitido', informe.emitido));
        partes.push(fila('Tickets', String(informe.tickets)));
        partes.push('</div>');

        partes.push('<div class="informe-x__bloque">');
        partes.push(fila('Base imponible', euros(informe.base)));
        partes.push(fila(informe.etiqueta_impuesto, euros(informe.impuesto)));
        partes.push(fila('Ventas', euros(informe.ventas), true));
        partes.push(fila('Ticket medio', euros(informe.ticket_medio)));
        partes.push('</div>');

        if (informe.medios.length) {
            partes.push('<div class="informe-x__bloque"><h4>Por medio de pago</h4>');

            informe.medios.forEach(function (medio) {
                partes.push(fila(medio.nombre, euros(medio.importe)));
            });

            partes.push('</div>');
        }

        partes.push('<div class="informe-x__bloque"><h4>Efectivo en caja</h4>');
        partes.push(fila('Fondo inicial', euros(informe.efectivo_inicial)));
        partes.push(fila('Ventas en efectivo', euros(informe.efectivo_ventas)));

        if (informe.entradas > 0) {
            partes.push(fila('Entradas', euros(informe.entradas)));
        }

        if (informe.salidas > 0) {
            partes.push(fila('Salidas', '-' + euros(informe.salidas)));
        }

        partes.push(fila('Debe haber', euros(informe.efectivo_teorico), true));
        partes.push('</div>');

        if (informe.por_profesional.length) {
            partes.push('<div class="informe-x__bloque"><h4>Por profesional</h4>');

            informe.por_profesional.forEach(function (linea) {
                partes.push(fila(linea.nombre, euros(linea.importe)));
            });

            partes.push('</div>');
        }

        if (informe.formacion > 0) {
            partes.push('<p class="informe-x__nota">' + informe.formacion
                + ' documento(s) de formación quedan fuera de este informe.</p>');
        }

        partes.push('<p class="informe-x__nota">'
            + 'Esto es una lectura: no cierra la jornada ni marca los tickets.'
            + '</p>');

        document.getElementById('cuerpoInformeX').innerHTML = partes.join('');
    }

    function fila(etiqueta, valor, fuerte) {
        return '<div class="informe-x__fila' + (fuerte ? ' informe-x__fila--fuerte' : '') + '">'
             + '<span>' + escapar(etiqueta) + '</span>'
             + '<span>' + escapar(valor) + '</span>'
             + '</div>';
    }

    var botonX = document.getElementById('accionInformeX');

    if (botonX && urlX) {
        botonX.addEventListener('click', function () {
            document.getElementById('cuerpoInformeX').innerHTML =
                '<p class="informe-x__cargando">Calculando…</p>';

            abrir('modalInformeX');

            pedir(urlX)
                .then(function (datos) { pintarX(datos.informe); })
                .catch(function (error) {
                    if (error.message === 'reauth') {
                        return;
                    }

                    document.getElementById('cuerpoInformeX').innerHTML =
                        '<p class="informe-x__nota">' + escapar(error.message) + '</p>';
                });
        });
    }

    var botonImprimirX = document.getElementById('imprimirInformeX');

    if (botonImprimirX && urlXImprimir) {
        botonImprimirX.addEventListener('click', function () {
            botonImprimirX.disabled = true;

            pedir(urlXImprimir, 'POST')
                .then(function (datos) {
                    cerrar(document.getElementById('modalInformeX'));
                    mensaje(datos.mensaje || 'Informe X enviado a la impresora.', false);
                })
                .catch(function (error) {
                    if (error.message !== 'reauth') {
                        mensaje(error.message, true);
                    }
                })
                .finally(function () { botonImprimirX.disabled = false; });
        });
    }

    // ---- Informe Z -------------------------------------------------

    function pintarZ(datos) {
        var partes = [];

        if (datos.ultimo) {
            partes.push('<div class="informe-x__bloque"><h4>Último cierre</h4>');
            partes.push(fila('Fecha', datos.ultimo.fecha));

            if (datos.ultimo.usuario) {
                partes.push(fila('Cerró', datos.ultimo.usuario));
            }

            partes.push(fila('Tickets', String(datos.ultimo.tickets)));
            partes.push(fila('Ventas', euros(datos.ultimo.ventas), true));
            partes.push(fila('Efectivo contado', euros(datos.ultimo.contado)));

            if (datos.ultimo.hay_ajuste) {
                partes.push(fila('Descuadre', euros(datos.ultimo.descuadre)));
            }

            partes.push('</div>');
        } else {
            partes.push('<p class="informe-x__nota">'
                + 'Todavía no hay ningún cierre hecho en este salón.'
                + '</p>');
        }

        partes.push('<div class="informe-x__bloque"><h4>Sin cerrar ahora mismo</h4>');
        partes.push(fila('Desde', datos.pendiente.desde));
        partes.push(fila('Tickets', String(datos.pendiente.tickets)));
        partes.push(fila('Ventas', euros(datos.pendiente.ventas), true));
        partes.push(fila('Efectivo que debe haber', euros(datos.pendiente.efectivo_teorico)));
        partes.push('</div>');

        partes.push('<p class="informe-x__nota">'
            + 'El cierre de verdad se hace en Caja, porque hay que contar el '
            + 'efectivo antes de cerrar. Desde aquí solo se consulta y se '
            + 'reimprime.</p>');

        partes.push('<div class="informe-x__acciones">');

        if (datos.ultimo) {
            partes.push('<button type="button" class="boton" data-imprimir-z="'
                + datos.ultimo.id + '">Imprimir el último Z</button>');
            partes.push('<a class="boton boton--secundario" href="'
                + datos.ultimo.url_detalle + '">Ver el detalle</a>');
        }

        if (datos.puede_cerrar && datos.pendiente.hay_movimientos) {
            partes.push('<a class="boton boton--secundario" href="' + datos.url_caja
                + '">Cerrar la jornada</a>');
        }

        partes.push('</div>');

        var cuerpo = document.getElementById('cuerpoInformeZ');
        cuerpo.innerHTML = partes.join('');

        var botonImprimirZ = cuerpo.querySelector('[data-imprimir-z]');

        if (botonImprimirZ && urlZImprimir) {
            botonImprimirZ.addEventListener('click', function () {
                botonImprimirZ.disabled = true;

                pedir(urlZImprimir.replace('__ID__', botonImprimirZ.dataset.imprimirZ), 'POST')
                    .then(function (respuesta) {
                        cerrar(document.getElementById('modalInformeZ'));
                        mensaje(respuesta.mensaje || 'Informe Z enviado a la impresora.', false);
                    })
                    .catch(function (error) {
                        if (error.message !== 'reauth') {
                            mensaje(error.message, true);
                        }
                    })
                    .finally(function () { botonImprimirZ.disabled = false; });
            });
        }
    }

    var botonZ = document.getElementById('accionInformeZ');

    if (botonZ && urlZ) {
        botonZ.addEventListener('click', function () {
            document.getElementById('cuerpoInformeZ').innerHTML =
                '<p class="informe-x__cargando">Cargando…</p>';

            abrir('modalInformeZ');

            pedir(urlZ)
                .then(pintarZ)
                .catch(function (error) {
                    if (error.message === 'reauth') {
                        return;
                    }

                    document.getElementById('cuerpoInformeZ').innerHTML =
                        '<p class="informe-x__nota">' + escapar(error.message) + '</p>';
                });
        });
    }
})();
</script>
@endpush

@endsection
