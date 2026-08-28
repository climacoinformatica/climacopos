@extends('panel.app')

@section('titulo', 'Festivos')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Festivos y cierres</h1>
        <p>
            {{ $festivos->count() }} en {{ $ano }} ·
            {{ $laborables }} caen en día que abres
        </p>
    </div>
</div>

@if ($proximos->isNotEmpty())
    <p class="aviso aviso--info">
        <strong>Próximos cierres:</strong>
        {{ $proximos->map(fn ($f) => $f->fecha->format('d/m') . ' ' . $f->nombre)->join(' · ') }}
    </p>
@endif

<form method="GET" class="filtros">
    <div class="campo">
        <label for="ano">Año</label>
        <select id="ano" name="ano" onchange="this.form.submit()">
            @foreach (range(now()->year - 1, now()->year + 2) as $a)
                <option value="{{ $a }}" @selected($ano === $a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>
</form>

{{-- ---------- Importar ---------- --}}
<div class="tarjeta" style="max-width:760px">
    <h2>Cargar los festivos de {{ $ano }}</h2>
    <p class="tarjeta__ayuda">
        Da de alta los nacionales, el Día de Canarias y la Semana Santa,
        que se calcula sola cada año.
    </p>

    <p class="aviso aviso--pendiente">
        <strong>Esto es una ayuda, no una fuente oficial.</strong>
        Los festivos locales de tu municipio y los traslados que aprueba
        cada comunidad no los sabemos: hay que añadirlos a mano mirando el
        calendario laboral publicado en el boletín.
    </p>

    <form method="POST" action="{{ route('panel.festivos.importar') }}">
        @csrf
        <input type="hidden" name="ano" value="{{ $ano }}">

        <div class="casilla">
            <input type="checkbox" id="canarias" name="canarias" value="1" checked>
            <div>
                <label for="canarias">Incluir el Día de Canarias</label>
                <small>Desmárcalo si el salón no está en Canarias.</small>
            </div>
        </div>

        <button type="submit" class="boton boton--pequeno">
            Cargar festivos de {{ $ano }}
        </button>
    </form>
</div>

{{-- ---------- Añadir ---------- --}}
<div class="tarjeta" style="max-width:760px">
    <h2>Añadir uno</h2>

    <form method="POST" action="{{ route('panel.festivos.guardar') }}">
        @csrf

        <div class="rejilla-campos">
            <div class="campo">
                <label for="fecha">Fecha *</label>
                <input type="date" id="fecha" name="fecha" required value="{{ old('fecha') }}">
            </div>

            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="120"
                       value="{{ old('nombre') }}" placeholder="Fiestas patronales">
            </div>

            <div class="campo">
                <label for="ambito">Ámbito</label>
                <select id="ambito" name="ambito" required>
                    @foreach (\App\Models\Festivo::AMBITOS as $clave => $texto)
                        <option value="{{ $clave }}" @selected($clave === 'LOCAL')>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="media_jornada">Media jornada</label>
                <select id="media_jornada" name="media_jornada">
                    <option value="">Cerrado todo el día</option>
                    <option value="MANANA">Abre solo por la mañana</option>
                    <option value="TARDE">Abre solo por la tarde</option>
                </select>
                <p class="campo__pista">
                    La media jornada queda anotada pero <strong>todavía no bloquea
                    la agenda</strong>: hace falta el horario especial, que aún no está.
                </p>
            </div>
        </div>

        <div class="campo">
            <label for="observaciones">Observaciones</label>
            <input type="text" id="observaciones" name="observaciones" maxlength="300">
        </div>

        <button type="submit" class="boton boton--pequeno">Añadir</button>
    </form>
</div>

{{-- ---------- Listado ---------- --}}
<div class="tarjeta" style="padding:.5rem">
    @if ($festivos->isEmpty())
        <p class="campo__pista" style="padding:1.5rem;text-align:center">
            No hay festivos cargados en {{ $ano }}.
        </p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Día</th><th>Nombre</th>
                        <th>Ámbito</th><th>Agenda</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($festivos as $festivo)
                    <tr @class(['fila-anulada' => $festivo->esPasado()])>
                        <td><strong>{{ $festivo->fecha->format('d/m/Y') }}</strong></td>
                        <td>
                            {{ $festivo->fecha->locale('es')->isoFormat('dddd') }}
                            @if ($festivo->fecha->isToday())
                                <span class="etiqueta">hoy</span>
                            @endif
                        </td>
                        <td>
                            {{ $festivo->nombre }}
                            @if ($festivo->observaciones)
                                <div style="color:var(--suave);font-size:.72rem">
                                    {{ $festivo->observaciones }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $festivo->etiquetaAmbito() }}</td>
                        <td>
                            @if ($festivo->cierraTodoElDia())
                                <span class="etiqueta">Cerrado</span>
                            @else
                                <span class="etiqueta etiqueta--inactivo">
                                    solo {{ strtolower($festivo->media_jornada) }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('panel.festivos.borrar', $festivo) }}"
                                  onsubmit="return confirm('¿Quitar este festivo? Ese día volverá a estar disponible.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="boton boton--secundario boton--pequeno">
                                    Quitar
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
