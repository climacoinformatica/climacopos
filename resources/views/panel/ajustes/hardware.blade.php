@extends('panel.app')

@section('titulo', 'Hardware')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Hardware</h1>
        <p>Impresoras, cajón y visor de cada terminal</p>
    </div>
    <a href="{{ route('panel.ajustes.ticket') }}" class="boton boton--secundario">Diseño del ticket</a>
</div>

@if (session('token_agente'))
    @php $t = session('token_agente'); @endphp
    <div class="tarjeta" style="border-color: var(--ok)">
        <h2>Token del agente para «{{ $t['terminal'] }}»</h2>
        <p class="tarjeta__ayuda">
            <strong>Cópialo ahora: no se vuelve a mostrar.</strong>
            Pégalo en el <code>config.ini</code> del Agente CLIMACO instalado en ese equipo.
        </p>

        <div class="campo">
            <label>URL del servidor</label>
            <input type="text" readonly value="{{ $t['url'] }}" style="font-family:monospace">
        </div>

        <div class="campo">
            <label>Token</label>
            <input type="text" readonly id="tokenAgente" value="{{ $t['token'] }}"
                   style="font-family:monospace;font-size:.85rem">
        </div>

        <button type="button" class="boton boton--pequeno" id="copiarToken">Copiar token</button>
    </div>
@endif

@foreach ($terminales as $terminal)
    @php
        $v = fn ($clave, $def = '') => old($clave, $terminal->ajuste($clave, $def));
    @endphp

    <div class="tarjeta">
        <h2>
            {{ $terminal->nombre }} ({{ $terminal->codigo }})
            @if ($actual && $actual->id === $terminal->id)
                <span class="etiqueta">Este equipo</span>
            @endif
        </h2>

        <p class="tarjeta__ayuda">
            @if ($terminal->agente_ultima_conexion)
                Agente visto por última vez {{ $terminal->agente_ultima_conexion->diffForHumans() }}.
                @if ($terminal->agente_ultima_conexion->diffInMinutes() > 5)
                    <strong style="color:var(--error)">Parece que no está funcionando.</strong>
                @endif
            @else
                El agente todavía no se ha conectado nunca en este terminal.
            @endif
        </p>

        <form method="POST" action="{{ route('panel.ajustes.terminal', $terminal) }}">
            @csrf

            <div class="campo">
                <label for="nombre{{ $terminal->id }}">Nombre del terminal</label>
                <input type="text" id="nombre{{ $terminal->id }}" name="nombre" required
                       value="{{ old('nombre', $terminal->nombre) }}">
            </div>

            <h3 class="subseccion">Impresora de tickets</h3>

            <div class="rejilla-campos">
                <div class="campo">
                    <label>Conexión</label>
                    <select name="impresora_tickets_modo" required>
                        <option value="RED"   @selected($v('impresora_tickets_modo', 'RED') === 'RED')>
                            Red — recomendado
                        </option>
                        <option value="LOCAL" @selected($v('impresora_tickets_modo') === 'LOCAL')>
                            USB o compartida en Windows
                        </option>
                    </select>

                    <p class="campo__pista">
                        <strong>Con impresora de red</strong>, cualquier ordenador
                        del salón puede imprimir, y las tablets funcionan sin
                        instalar nada.
                    </p>
                    <p class="campo__pista">
                        <strong>Con USB o compartida</strong>, solo imprime el
                        equipo que tiene la impresora enchufada. Si ese ordenador
                        está apagado, no sale ningún ticket.
                    </p>
                </div>

                <div class="campo">
                    <label>IP de la impresora</label>
                    <input type="text" name="impresora_tickets_ip" placeholder="192.168.1.50"
                           value="{{ $v('impresora_tickets_ip') }}">
                    <p class="campo__pista">
                        La ves en la hoja de autotest: apaga la impresora, mantén
                        pulsado el botón de avance de papel y vuelve a encenderla.
                        Sale un papel con la dirección.
                    </p>
                    <p class="campo__pista">
                        Conviene <strong>fijarla en el router</strong> para que no
                        cambie sola. Si un día deja de imprimir sin motivo, suele
                        ser eso.
                    </p>
                </div>

                <div class="campo">
                    <label>Puerto</label>
                    <input type="number" name="impresora_tickets_puerto"
                           value="{{ $v('impresora_tickets_puerto', 9100) }}">
                </div>

                <div class="campo">
                    <label>Recurso compartido</label>
                    <input type="text" name="impresora_tickets_local" placeholder="\\MIPC\TICKETS"
                           value="{{ $v('impresora_tickets_local') }}">
                    <p class="campo__pista">
                        Solo si elegiste USB o compartida. Hay que compartirla
                        antes en Windows.
                    </p>
                </div>

                <div class="campo">
                    <label>Ancho del papel</label>
                    <select name="impresora_ancho_mm" required>
                        <option value="80" @selected((string) $v('impresora_ancho_mm', 80) === '80')>80 mm (48 columnas)</option>
                        <option value="58" @selected((string) $v('impresora_ancho_mm') === '58')>58 mm (32 columnas)</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Imprimir el ticket al cobrar</label>
                    <select name="ticket_imprimir">
                        @foreach ([
                            'SIEMPRE'   => 'Siempre',
                            'PREGUNTAR' => 'Preguntar cada vez',
                            'NUNCA'     => 'Nunca, solo si lo piden',
                        ] as $clave => $texto)
                            <option value="{{ $clave }}"
                                    @selected($v('ticket_imprimir', 'SIEMPRE') === $clave)>
                                {{ $texto }}
                            </option>
                        @endforeach
                    </select>
                    <p class="campo__pista">
                        Con <strong>«preguntar»</strong>, tras cobrar aparece un botón
                        y decide quien atiende. Con <strong>«nunca»</strong>, el ticket
                        se puede imprimir después si la clienta lo pide.
                    </p>
                    <p class="campo__pista">
                        La factura se registra y se envía a Hacienda
                        <strong>se imprima o no</strong>: esto solo decide si sale
                        el papel.
                    </p>
                </div>

            </div>

            <h3 class="subseccion">Cajón portamonedas</h3>

            <div class="rejilla-campos">
                <div class="campo">
                    <label>Cómo se abre</label>
                    <select name="cajon_modo" required>
                        <option value="IMPRESORA" @selected($v('cajon_modo', 'IMPRESORA') === 'IMPRESORA')>Conectado a la impresora</option>
                        <option value="SERIE"     @selected($v('cajon_modo') === 'SERIE')>Puerto serie propio</option>
                        <option value="NINGUNO"   @selected($v('cajon_modo') === 'NINGUNO')>No hay cajón</option>
                    </select>
                </div>

                <div class="campo">
                    <label>Pin</label>
                    <select name="cajon_pin" required>
                        <option value="2" @selected((string) $v('cajon_pin', 2) === '2')>Pin 2 (lo habitual)</option>
                        <option value="5" @selected((string) $v('cajon_pin') === '5')>Pin 5</option>
                    </select>
                    <p class="campo__pista">Si no abre, prueba el otro pin: es la causa en nueve de cada diez casos.</p>
                </div>

                <div class="campo">
                    <label>Puerto serie del cajón</label>
                    <input type="text" name="cajon_puerto" placeholder="COM2" value="{{ $v('cajon_puerto') }}">
                </div>
            </div>

            <h3 class="subseccion">Pantalla</h3>

            <div class="rejilla-campos">
                <div class="campo">
                    <label>Teclado en pantalla</label>
                    <select name="teclado_tactil">
                        <option value="auto"    @selected($v('teclado_tactil', 'auto') === 'auto')>
                            Automático — si la pantalla es táctil
                        </option>
                        <option value="siempre" @selected($v('teclado_tactil') === 'siempre')>
                            Siempre
                        </option>
                        <option value="nunca"   @selected($v('teclado_tactil') === 'nunca')>
                            Nunca
                        </option>
                    </select>
                    <p class="campo__pista">
                        Aparece al tocar cualquier campo de importe, PIN o contraseña.
                        En el PC del despacho, con su teclado de siempre, conviene
                        ponerlo en «Nunca».
                    </p>
                </div>
            </div>

            <h3 class="subseccion">Visor de cliente</h3>

            <div class="rejilla-campos">
                <div class="campo">
                    <label>Puerto</label>
                    <input type="text" name="visor_puerto" placeholder="COM3" value="{{ $v('visor_puerto') }}">
                </div>

                <div class="campo">
                    <label>Baudios</label>
                    <select name="visor_baudios">
                        @foreach ([2400, 4800, 9600, 19200, 38400] as $baudios)
                            <option value="{{ $baudios }}" @selected((string) $v('visor_baudios', 9600) === (string) $baudios)>
                                {{ $baudios }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label>Texto en reposo, línea 1</label>
                    <input type="text" name="visor_linea1_reposo" maxlength="20"
                           value="{{ $v('visor_linea1_reposo', tenant('nombre_comercial')) }}">
                </div>

                <div class="campo">
                    <label>Texto en reposo, línea 2</label>
                    <input type="text" name="visor_linea2_reposo" maxlength="20"
                           value="{{ $v('visor_linea2_reposo', 'Bienvenido') }}">
                </div>

                <div class="campo">
                    <label>Sondeo del agente (ms)</label>
                    <input type="number" name="agente_intervalo_ms" min="500" max="10000" step="500"
                           value="{{ $v('agente_intervalo_ms', 1500) }}">
                </div>
            </div>

            <button type="submit" class="boton boton--pequeno">Guardar configuración</button>
        </form>

        {{--
            Aviso cuando la impresora esta atada a un equipo concreto.

            Es una fuente de soporte segura: el salon compra tablets, las
            reparte, y un dia el ordenador de caja esta apagado y nadie
            entiende por que no salen los tickets.
        --}}
        @if ($v('impresora_tickets_modo', 'RED') === 'LOCAL')
            <p class="aviso aviso--pendiente" style="margin-top:1rem">
                <strong>Esta impresora está atada a un ordenador.</strong>
                Solo saldrán tickets cuando ese equipo esté encendido y con
                la sesión abierta. Si trabajáis con tablets o con más de un
                ordenador, una impresora de red os evitará problemas.
            </p>
        @endif

        {{-- ---------- Conector de impresión ---------- --}}
        <div class="conector">
            <h3 class="subseccion">Conector de impresión</h3>

            @if ($terminal->agente_ultima_conexion)
                <p class="conector__estado conector__estado--ok">
                    Funcionando. Última señal
                    {{ $terminal->agente_ultima_conexion->diffForHumans() }}.
                </p>
            @else
                <p class="conector__estado conector__estado--falta">
                    <strong>Todavía no está instalado en este equipo.</strong>
                    Sin él los tickets no salen por la impresora.
                </p>
            @endif

            <p class="conector__nota">
                Va instalado en <strong>un ordenador con Windows</strong> del
                salón, el que esté siempre encendido. Es el que manda los
                tickets a la impresora.
            </p>
            <p class="conector__nota">
                Con impresora de red basta con ese equipo: las tablets y los
                demás ordenadores imprimen a través de él, sin instalar nada.
            </p>

            <ol class="conector__pasos">
                <li>Descarga el conector y ábrelo con doble clic.</li>
                <li>Elige tu impresora de la lista.</li>
                <li>Listo. Se abre solo cada vez que enciendas el ordenador.</li>
            </ol>

            <a href="{{ route('panel.conector.descargar', $terminal) }}"
               class="boton boton--marca">
                Descargar el conector
            </a>

            <details class="conector__ayuda">
                <summary>Windows dice que el archivo no es seguro</summary>

                <p>
                    Es normal y no significa que haya nada malo: pasa con todos
                    los programas que no llevan un certificado de pago.
                </p>
                <p>
                    En el aviso de Windows, pulsa <strong>Más información</strong>
                    y luego <strong>Ejecutar de todas formas</strong>. Si tu
                    antivirus lo bloquea, desactívalo un momento mientras lo
                    instalas.
                </p>
                <p>
                    Descárgalo siempre desde aquí, desde tu propio panel. Uno que
                    te pase otra persona no funcionará: cada salón tiene el suyo.
                </p>
            </details>
        </div>


{{--
    Este ajuste va en su PROPIA tarjeta y su propio formulario.

    Estaba dentro del formulario del terminal, y en HTML los
    formularios no se pueden anidar: el navegador los fusiona y manda
    todo junto, asi que al guardar esto se validaba tambien el del
    terminal y saltaba «The teclado tactil field is required».
--}}
<div class="tarjeta" style="max-width:640px">
    <h2>Al terminar un cobro</h2>

    <form method="POST" action="{{ route('panel.ajustes.salon') }}">
        @csrf

        <div class="campo">
            <label for="trasCobrar">Qué hacer después de cobrar</label>
            <select name="tras_cobrar" id="trasCobrar">
                @foreach ([
                    'NADA'     => 'Quedarse en el punto de venta',
                    'SELECTOR' => 'Volver a elegir usuario',
                    'INICIO'   => 'Volver al menú principal',
                ] as $clave => $texto)
                    <option value="{{ $clave }}"
                            @selected((tenant('tras_cobrar') ?: 'NADA') === $clave)>
                        {{ $texto }}
                    </option>
                @endforeach
            </select>

            <p class="campo__pista">
                Con un solo ordenador y varios profesionales que cobran lo
                suyo, <strong>«volver a elegir usuario»</strong> hace que cada
                uno meta su PIN y lo que teclee se le asigne solo. En un salón
                con recepción, donde una persona cobra todo el día, déjalo en
                «quedarse en el punto de venta».
            </p>
            <p class="campo__pista">
                Este ajuste es del salón entero, no de este terminal.
            </p>
        </div>

        <button type="submit" class="boton boton--pequeno">Guardar</button>
    </form>
</div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--borde)">
            @foreach (['TICKET' => 'Imprimir prueba', 'CAJON' => 'Abrir cajón', 'VISOR' => 'Probar visor'] as $que => $texto)
                <form method="POST" action="{{ route('panel.ajustes.probar', $terminal) }}">
                    @csrf
                    <input type="hidden" name="que" value="{{ $que }}">
                    <button type="submit" class="boton boton--secundario boton--pequeno">{{ $texto }}</button>
                </form>
            @endforeach

            <form method="POST" action="{{ route('panel.ajustes.token', $terminal) }}"
                  onsubmit="return confirm('Se generará un token nuevo y el anterior dejará de funcionar. ¿Continuar?')">
                @csrf
                <button type="submit" class="boton boton--pequeno">Generar token del agente</button>
            </form>
        </div>
    </div>
@endforeach

{{-- ---------- Cola ---------- --}}
<div class="tarjeta">
    <h2>Cola de impresión</h2>
    <p class="tarjeta__ayuda">
        Los trabajos esperan aquí hasta que el agente los recoge. Si el equipo está
        apagado, no se pierden: se imprimen al arrancarlo.
    </p>

    @if ($cola->isEmpty())
        <p class="campo__pista">No hay trabajos.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Hora</th><th>Terminal</th><th>Tipo</th>
                        <th>Descripción</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($cola as $trabajo)
                    <tr>
                        <td>{{ $trabajo->created_at->format('H:i:s') }}</td>
                        <td>{{ $trabajo->terminal?->nombre }}</td>
                        <td>{{ ucfirst(strtolower($trabajo->tipo)) }}</td>
                        <td>
                            {{ $trabajo->descripcion }}
                            @if ($trabajo->error)
                                <div style="color:var(--error);font-size:.72rem">{{ $trabajo->error }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="etiqueta {{ $trabajo->estado === 'ERROR' ? 'etiqueta--inactivo' : '' }}">
                                {{ ucfirst(strtolower($trabajo->estado)) }}
                            </span>
                            @if ($trabajo->intentos > 1)
                                <small style="color:var(--suave)">{{ $trabajo->intentos }} intentos</small>
                            @endif
                        </td>
                        <td>
                            @if (in_array($trabajo->estado, ['ERROR', 'HECHO']))
                                <form method="POST" action="{{ route('panel.ajustes.reintentar', $trabajo) }}">
                                    @csrf
                                    <button type="submit" class="boton boton--secundario boton--pequeno">
                                        Reimprimir
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('panel.ajustes.purgar') }}" style="margin-top:1rem">
            @csrf
            <button type="submit" class="boton boton--secundario boton--pequeno">
                Limpiar trabajos completados
            </button>
        </form>
    @endif
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/conector.css') }}?v=29">
<script>
document.getElementById('copiarToken')?.addEventListener('click', function () {
    const campo = document.getElementById('tokenAgente');
    campo.select();
    navigator.clipboard.writeText(campo.value).then(() => {
        this.textContent = 'Copiado';
    });
});
</script>
<style>
.subseccion {
    font-size: .78rem; text-transform: uppercase; letter-spacing: .5px;
    color: var(--suave); margin: 1.25rem 0 .75rem;
    padding-top: .75rem; border-top: 1px solid var(--panel2);
}
</style>
@endpush


@endsection
