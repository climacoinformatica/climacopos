@extends('portal.base')

@section('titulo', $articulo->nombre)

@section('pasos')
    <a href="{{ route('portal.inicio') }}" class="paso paso--hecho">1. Servicio</a>
    <span class="paso paso--activo">2. Día y hora</span>
    <span class="paso">3. Tus datos</span>
@endsection

@section('contenido')

<div class="resumen-servicio">
    <div>
        <h1 class="portal-titulo">{{ $articulo->nombre }}</h1>
        <p class="resumen-servicio__meta">
            {{ $articulo->duracionTotal() }} min ·
            {{ number_format($articulo->precio, 2, ',', '.') }} €
        </p>
    </div>
    <a href="{{ route('portal.inicio') }}" class="enlace-suave">Cambiar</a>
</div>

{{-- ---------- Profesional ---------- --}}
@if ($profesionales->count() > 1)
    <section class="bloque">
        <h2 class="bloque__titulo">¿Con quién?</h2>
        <div class="fichas">
            <a href="{{ route('portal.hueco', ['articulo' => $articulo, 'fecha' => $fecha->toDateString()]) }}"
               class="ficha {{ ! $profesional ? 'ficha--activa' : '' }}">
                <span class="ficha__avatar ficha__avatar--todos">✓</span>
                <span>Me da igual</span>
            </a>

            @foreach ($profesionales as $candidato)
                <a href="{{ route('portal.hueco', ['articulo' => $articulo, 'fecha' => $fecha->toDateString(), 'usuario_id' => $candidato->id]) }}"
                   class="ficha {{ $profesional?->id === $candidato->id ? 'ficha--activa' : '' }}">
                    <span class="ficha__avatar" style="--color: {{ $candidato->color_agenda }}">
                        @if ($candidato->foto)
                            <img src="{{ tenant_asset($candidato->foto) }}" alt="">
                        @else
                            {{ $candidato->iniciales() }}
                        @endif
                    </span>
                    <span>{{ $candidato->alias ?: $candidato->nombre }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- ---------- Días ---------- --}}
<section class="bloque">
    <h2 class="bloque__titulo">¿Qué día?</h2>

    <div class="dias">
        @for ($i = 0; $i < 14; $i++)
            @php
                $dia = now()->startOfDay()->addDays($i);
                $activo = $dia->isSameDay($fecha);
            @endphp
            @if ($dia->lte($limite))
                <a href="{{ route('portal.hueco', array_filter([
                        'articulo'   => $articulo->id,
                        'fecha'      => $dia->toDateString(),
                        'usuario_id' => $profesional?->id,
                   ])) }}"
                   class="dia {{ $activo ? 'dia--activo' : '' }}">
                    <span class="dia__semana">{{ $dia->locale('es')->isoFormat('ddd') }}</span>
                    <span class="dia__numero">{{ $dia->day }}</span>
                    <span class="dia__mes">{{ $dia->locale('es')->isoFormat('MMM') }}</span>
                </a>
            @endif
        @endfor
    </div>
</section>

{{-- ---------- Horas ---------- --}}
<section class="bloque">
    <h2 class="bloque__titulo">
        {{ $fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}
    </h2>

    @if ($huecos === [])
        <div class="sin-huecos">
            <p>No queda hueco este día.</p>

            @if ($sugerido)
                <a href="{{ route('portal.hueco', array_filter([
                        'articulo'   => $articulo->id,
                        'fecha'      => $sugerido->toDateString(),
                        'usuario_id' => $profesional?->id,
                   ])) }}" class="boton-portal">
                    Ver el {{ $sugerido->locale('es')->isoFormat('dddd D [de] MMMM') }}
                </a>
            @elseif ($profesional)
                <p class="sin-huecos__pista">
                    Prueba con otro profesional o con otra fecha.
                </p>
            @endif
        </div>
    @else
        @php
            $manana = array_filter(array_keys($huecos), fn ($h) => $h < '14:00');
            $tarde  = array_filter(array_keys($huecos), fn ($h) => $h >= '14:00');
        @endphp

        @foreach (['Mañana' => $manana, 'Tarde' => $tarde] as $franja => $lista)
            @if ($lista !== [])
                <h3 class="franja-titulo">{{ $franja }}</h3>
                <div class="horas">
                    @foreach ($lista as $hora)
                        <form method="POST" action="{{ route('portal.datos', $articulo) }}">
                            @csrf
                            <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                            <input type="hidden" name="hora" value="{{ $hora }}">
                            @if ($profesional)
                                <input type="hidden" name="usuario_id" value="{{ $profesional->id }}">
                            @endif
                            <button type="submit" class="hora">{{ $hora }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif
</section>

@endsection
