@extends('portal.base')

@section('titulo', 'Reservar cita')

@section('pasos')
    <span class="paso paso--activo">1. Servicio</span>
    <span class="paso">2. Día y hora</span>
    <span class="paso">3. Tus datos</span>
@endsection

@section('contenido')

<h1 class="portal-titulo">¿Qué te apetece hoy?</h1>

@if ($familias->isEmpty())
    <div class="vacio-portal">
        <p>Todavía no hay servicios disponibles para reservar por internet.</p>
        @if ($empresa->telefono)
            <a href="tel:{{ $empresa->telefono }}" class="boton-portal">Llámanos al {{ $empresa->telefono }}</a>
        @endif
    </div>
@endif

@foreach ($familias as $familia)
    <section class="familia">
        <h2 class="familia__titulo">
            <span class="familia__punto" style="background:{{ $familia->color }}"></span>
            {{ $familia->nombre }}
        </h2>

        <ul class="servicios">
            @foreach ($familia->articulos as $articulo)
                <li>
                    <a href="{{ route('portal.hueco', $articulo) }}" class="servicio">
                        @if ($url = $articulo->urlFoto())
                            <img src="{{ $url }}" alt="" class="servicio__foto" loading="lazy">
                        @else
                            <span class="servicio__foto servicio__foto--vacia"
                                  style="background:{{ $familia->color }}22"></span>
                        @endif

                        <span class="servicio__datos">
                            <strong>{{ $articulo->nombre }}</strong>
                            @if ($articulo->descripcion_online)
                                <small>{{ $articulo->descripcion_online }}</small>
                            @endif
                            <span class="servicio__meta">
                                {{ $articulo->duracionTotal() }} min
                                @if ($articulo->politica_pago !== 'NINGUNO')
                                    · <em>{{ $articulo->politica_pago === 'TOTAL' ? 'pago online' : 'con fianza' }}</em>
                                @endif
                            </span>
                        </span>

                        <span class="servicio__precio">
                            {{ number_format($articulo->precio, 2, ',', '.') }} €
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endforeach

@endsection
