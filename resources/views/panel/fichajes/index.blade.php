@extends('panel.app')

@section('titulo', 'Mi jornada')

@php
    $horas = fn ($m) => \App\Services\GestorFichajes::horasYMinutos($m);
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Mi jornada</h1>
        <p>{{ now()->locale('es')->isoFormat('dddd D [de] MMMM') }}</p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="{{ route('panel.fichajes.mio') }}" class="boton boton--secundario">
            Mi registro
        </a>

        @if ($usuarioSalon->tienePermiso(\App\Support\Permisos::USUARIOS_GESTIONAR))
            <a href="{{ route('panel.fichajes.informe') }}" class="boton boton--secundario">
                Informe del equipo
            </a>
        @endif
    </div>
</div>

{{-- ---------- Botón grande ---------- --}}
<div class="fichaje-panel">
    <div class="fichaje-estado fichaje-estado--{{ strtolower($estado) }}">
        <span class="fichaje-estado__punto"></span>
        {{ match ($estado) {
            'TRABAJANDO' => 'Estás trabajando',
            'PAUSA'      => 'Estás en pausa',
            default      => 'Fuera de jornada',
        } }}
    </div>

    <p class="fichaje-reloj" id="reloj">{{ now()->format('H:i') }}</p>

    <div class="fichaje-botones">
        @if ($estado === 'FUERA')
            <form method="POST" action="{{ route('panel.fichajes.fichar') }}">
                @csrf
                <input type="hidden" name="tipo" value="ENTRADA">
                <button type="submit" class="boton-fichar boton-fichar--entrada">
                    Fichar entrada
                </button>
            </form>
        @elseif ($estado === 'TRABAJANDO')
            <form method="POST" action="{{ route('panel.fichajes.fichar') }}">
                @csrf
                <input type="hidden" name="tipo" value="PAUSA_INICIO">
                <button type="submit" class="boton-fichar boton-fichar--pausa">
                    Empezar pausa
                </button>
            </form>

            <form method="POST" action="{{ route('panel.fichajes.fichar') }}">
                @csrf
                <input type="hidden" name="tipo" value="SALIDA">
                <button type="submit" class="boton-fichar boton-fichar--salida">
                    Fichar salida
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.fichajes.fichar') }}">
                @csrf
                <input type="hidden" name="tipo" value="PAUSA_FIN">
                <button type="submit" class="boton-fichar boton-fichar--entrada">
                    Terminar pausa
                </button>
            </form>
        @endif
    </div>

    <p class="fichaje-hoy">
        Hoy llevas <strong>{{ $horas($jornada['minutos']) }}</strong>
        @if ($jornada['pausa'] > 0)
            · {{ $jornada['pausa'] }} min de pausa
        @endif
    </p>
</div>

{{-- ---------- Fichajes de hoy ---------- --}}
<div class="tarjeta">
    <h2>Fichajes de hoy</h2>

    @if ($jornada['fichajes']->isEmpty())
        <p class="campo__pista">Todavía no has fichado hoy.</p>
    @else
        <ul class="fichajes-lista">
            @foreach ($jornada['fichajes'] as $fichaje)
                <li class="fichaje-item fichaje-item--{{ strtolower($fichaje->tipo) }}">
                    <span class="fichaje-item__hora">{{ $fichaje->hora() }}</span>
                    <span class="fichaje-item__tipo">{{ $fichaje->etiqueta() }}</span>
                    @if ($fichaje->esManual())
                        <span class="etiqueta etiqueta--inactivo">manual</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($jornada['incompleta'])
        <p class="aviso aviso--pendiente" style="margin-top:1rem">
            Falta algún fichaje del día. Si has olvidado uno, díselo a
            tu responsable: se puede añadir dejando constancia.
        </p>
    @endif
</div>

{{-- ---------- Semana ---------- --}}
<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Esta semana</h2>
        <strong>{{ $horas($semana['total']) }}</strong>
    </div>

    <div class="semana-barras">
        @php $maximo = max(1, collect($semana['dias'])->max('minutos')); @endphp

        @foreach ($semana['dias'] as $dia)
            <div class="semana-dia {{ $dia['hoy'] ? 'semana-dia--hoy' : '' }}">
                <div class="semana-dia__barra">
                    <span style="height: {{ $dia['futuro'] ? 0 : ($dia['minutos'] / $maximo) * 100 }}%"
                          @class(['semana-dia__relleno--aviso' => $dia['incompleta']])></span>
                </div>
                <small>{{ $dia['fecha']->locale('es')->isoFormat('dd') }}</small>
                <small class="semana-dia__horas">
                    {{ $dia['minutos'] > 0 ? round($dia['minutos'] / 60, 1) . ' h' : '—' }}
                </small>
            </div>
        @endforeach
    </div>
</div>

{{-- ---------- Quién está dentro ---------- --}}
@if ($dentro->isNotEmpty())
    <div class="tarjeta">
        <h2>Ahora mismo en el salón</h2>

        <div class="dentro-lista">
            @foreach ($dentro as $persona)
                <div class="dentro-persona">
                    <span class="punto-color" style="background:{{ $persona->color_agenda }}"></span>
                    {{ $persona->nombre }}
                </div>
            @endforeach
        </div>
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/fichajes.css') }}?v=18">
<script>
// Reloj en marcha: da sensación de que el fichaje se registra al momento
setInterval(function () {
    const ahora = new Date();
    document.getElementById('reloj').textContent =
        String(ahora.getHours()).padStart(2, '0') + ':' +
        String(ahora.getMinutes()).padStart(2, '0');
}, 10000);
</script>
@endpush

@endsection
