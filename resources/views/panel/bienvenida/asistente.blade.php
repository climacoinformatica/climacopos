@extends('panel.base')

@section('titulo', 'Bienvenida')

@section('contenido')

<div class="asistente">

    <header class="asistente__cabecera">
        <h1>Vamos a poner en marcha {{ $empresa->nombre_comercial }}</h1>
        <p>Cuatro pasos. Cinco minutos. Después ya puedes dar citas.</p>

        <ol class="asistente__pasos">
            @foreach (['Datos fiscales', 'Horario', 'Servicios', 'Listo'] as $i => $titulo)
                @php $numero = $i + 1; @endphp

                <li @class([
                    'asistente__paso',
                    'asistente__paso--hecho'  => $numero < $paso,
                    'asistente__paso--activo' => $numero === $paso,
                ])>
                    <span>{{ $numero < $paso ? '✓' : $numero }}</span>
                    {{ $titulo }}
                </li>
            @endforeach
        </ol>
    </header>

    @if (session('exito'))
        <p class="mensaje mensaje--ok">{{ session('exito') }}</p>
    @endif

    @if ($errors->any())
        <div class="mensaje mensaje--error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- ================= Paso 1 ================= --}}
    @if ($paso === 1)
        <div class="asistente__tarjeta">
            <h2>Los datos de tu negocio</h2>
            <p class="asistente__ayuda">
                Salen en cada factura y se envían a Hacienda, así que tienen
                que coincidir con los de tu alta censal. Si algo no cuadra,
                mejor comprobarlo ahora que después de emitir cien tickets.
            </p>

            <form method="POST" action="{{ route('panel.bienvenida.fiscales') }}">
                @csrf

                <div class="campo">
                    <label for="razon_social">Razón social o nombre completo *</label>
                    <input type="text" id="razon_social" name="razon_social" required
                           maxlength="150" value="{{ old('razon_social', $empresa->razon_social) }}">
                    <small>El nombre con el que estás dado de alta, no el comercial.</small>
                </div>

                <div class="campo">
                    <label for="nif">NIF *</label>
                    <input type="text" id="nif" name="nif" required maxlength="20"
                           value="{{ old('nif', $empresa->nif) }}"
                           style="text-transform:uppercase">
                </div>

                <div class="campo">
                    <label for="direccion">Dirección *</label>
                    <input type="text" id="direccion" name="direccion" required maxlength="200"
                           value="{{ old('direccion', $empresa->direccion) }}">
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="codigo_postal">Código postal *</label>
                        <input type="text" id="codigo_postal" name="codigo_postal" required
                               maxlength="10" inputmode="numeric"
                               value="{{ old('codigo_postal', $empresa->codigo_postal) }}">
                    </div>

                    <div class="campo">
                        <label for="poblacion">Población *</label>
                        <input type="text" id="poblacion" name="poblacion" required maxlength="100"
                               value="{{ old('poblacion', $empresa->poblacion) }}">
                    </div>

                    <div class="campo">
                        <label for="provincia">Provincia *</label>
                        <input type="text" id="provincia" name="provincia" required maxlength="60"
                               value="{{ old('provincia', $empresa->provincia) }}">
                    </div>

                    <div class="campo">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" maxlength="30"
                               value="{{ old('telefono', $empresa->telefono) }}">
                    </div>
                </div>

                <button type="submit" class="boton boton--grande">Continuar</button>
            </form>
        </div>
    @endif

    {{-- ================= Paso 2 ================= --}}
    @if ($paso === 2)
        <div class="asistente__tarjeta">
            <h2>¿Cuándo abres?</h2>
            <p class="asistente__ayuda">
                Con esto la agenda ya sabe qué huecos ofrecer. Si algún día
                tienes otro horario, se ajusta después sin problema.
            </p>

            <form method="POST" action="{{ route('panel.bienvenida.horario') }}">
                @csrf

                <div class="campo">
                    <label>Días de apertura *</label>

                    <div class="dias-semana">
                        @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
                                   4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'] as $num => $dia)
                            <label class="dia-casilla">
                                <input type="checkbox" name="dias[]" value="{{ $num }}"
                                       @checked(in_array($num, [2, 3, 4, 5, 6]))>
                                <span>{{ $dia }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="hora_ini">Abres a las *</label>
                        <input type="time" id="hora_ini" name="hora_ini" required
                               value="{{ old('hora_ini', '09:00') }}">
                    </div>

                    <div class="campo">
                        <label for="hora_fin">Cierras a las *</label>
                        <input type="time" id="hora_fin" name="hora_fin" required
                               value="{{ old('hora_fin', '19:00') }}">
                    </div>
                </div>

                <p class="campo__pista">
                    Si cierras a mediodía, ponlo de corrido por ahora y luego
                    ajústalo en Horarios: ahí se pueden poner dos tramos.
                </p>

                <button type="submit" class="boton boton--grande">Continuar</button>
            </form>
        </div>
    @endif

    {{-- ================= Paso 3 ================= --}}
    @if ($paso === 3)
        <div class="asistente__tarjeta">
            <h2>Tus servicios</h2>
            <p class="asistente__ayuda">
                Los tres o cuatro más habituales bastan para empezar. La
                duración es la que ocupan en agenda, y el precio ya lleva el
                impuesto incluido, como se lo dices a la clienta.
            </p>

            <form method="POST" action="{{ route('panel.bienvenida.servicios') }}">
                @csrf

                <div id="servicios">
                    @foreach ([['Corte', 18, 45], ['Color', 45, 90], ['Peinado', 22, 40], ['', '', '']] as $i => $ejemplo)
                        <div class="fila-servicio">
                            <input type="text" name="servicios[{{ $i }}][nombre]"
                                   placeholder="Nombre del servicio" maxlength="120"
                                   value="{{ $ejemplo[0] }}">

                            <input type="number" name="servicios[{{ $i }}][precio]"
                                   placeholder="Precio" step="0.01" min="0"
                                   value="{{ $ejemplo[1] }}" inputmode="decimal">

                            <input type="number" name="servicios[{{ $i }}][duracion]"
                                   placeholder="Minutos" min="5" max="600"
                                   value="{{ $ejemplo[2] }}" inputmode="numeric">

                            <input type="hidden" name="servicios[{{ $i }}][impuesto]" value="7">
                        </div>
                    @endforeach
                </div>

                <p class="campo__pista">
                    Puedes borrar los de ejemplo y poner los tuyos. Después se
                    añaden todos los que quieras desde el catálogo.
                </p>

                <button type="submit" class="boton boton--grande">Continuar</button>
            </form>
        </div>
    @endif

    {{-- ================= Paso 4 ================= --}}
    @if ($paso >= 4)
        <div class="asistente__tarjeta asistente__tarjeta--final">
            <div class="icono-final">✓</div>

            <h2>Ya está</h2>

            <p class="asistente__ayuda">
                Tu salón está listo para dar citas y cobrar. Lo que queda son
                cosas que puedes hacer con calma:
            </p>

            <ul class="lista-siguiente">
                <li>Añadir a tu equipo, desde <strong>Usuarios</strong></li>
                <li>Completar el catálogo, en <strong>Catálogo</strong></li>
                <li>Activar la reserva online, en <strong>Ajustes</strong></li>
                <li>Configurar la impresora de tickets, en <strong>Hardware</strong></li>
                <li>Revisar VERI*FACTU con tu asesor antes de facturar de verdad</li>
            </ul>

            <form method="POST" action="{{ route('panel.bienvenida.terminar') }}">
                @csrf
                <button type="submit" class="boton boton--grande">Empezar a trabajar</button>
            </form>
        </div>
    @endif

    @if ($paso < 4)
        <form method="POST" action="{{ route('panel.bienvenida.saltar') }}" class="asistente__saltar">
            @csrf
            <button type="submit">Lo configuro más tarde</button>
        </form>
    @endif

</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/asistente.css') }}?v=22">
@endpush

@endsection
