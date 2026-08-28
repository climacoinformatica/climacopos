@extends('panel.app')

@section('titulo', 'Bonos')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Bonos y packs</h1>
        <p>Lo que el salón pone a la venta</p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="{{ route('panel.bonos.vendidos') }}" class="boton boton--secundario">Bonos vendidos</a>
        <a href="{{ route('panel.bonos.crear') }}" class="boton">Nuevo bono</a>
    </div>
</div>

@if ($plantillas->isEmpty())
    <div class="vacio">
        <h3>Todavía no hay bonos</h3>
        <p>
            Un bono es la forma más directa de que una clienta pague por adelantado
            y vuelva. «5 manicuras por 60 €» asegura cinco visitas.
        </p>
        <a href="{{ route('panel.bonos.crear') }}" class="boton">Crear el primero</a>
    </div>
@else
    <div class="rejilla-bonos">
        @foreach ($plantillas as $plantilla)
            <div class="tarjeta-bono" style="--color: {{ $plantilla->color }}"
                 @class(['tarjeta-bono--inactivo' => ! $plantilla->activo])>

                <div class="tarjeta-bono__cabecera">
                    <h3>{{ $plantilla->nombre }}</h3>
                    @unless ($plantilla->activo)
                        <span class="etiqueta etiqueta--inactivo">No se vende</span>
                    @endunless
                </div>

                <p class="tarjeta-bono__precio">
                    {{ number_format($plantilla->precio, 2, ',', '.') }} €
                </p>

                <p class="tarjeta-bono__detalle">
                    @if ($plantilla->esDeSesiones())
                        {{ $plantilla->num_sesiones }} sesiones
                        @if ($plantilla->articulo)
                            de {{ $plantilla->articulo->nombre }}
                        @elseif ($plantilla->familia)
                            de {{ $plantilla->familia->nombre }}
                        @endif
                        @if ($plantilla->precioPorSesion())
                            <br><small>{{ number_format($plantilla->precioPorSesion(), 2, ',', '.') }} € por sesión</small>
                        @endif
                    @else
                        {{ number_format($plantilla->saldo_otorgado, 2, ',', '.') }} € de saldo
                        @if ($plantilla->familia)
                            <br><small>solo para {{ $plantilla->familia->nombre }}</small>
                        @endif
                    @endif
                </p>

                @if ($plantilla->ahorro() > 0)
                    <p class="tarjeta-bono__ahorro">
                        La clienta ahorra {{ number_format($plantilla->ahorro(), 2, ',', '.') }} €
                    </p>
                @endif

                <ul class="tarjeta-bono__datos">
                    <li>
                        @if ($plantilla->caducidad_meses)
                            Caduca a los {{ $plantilla->caducidad_meses }} meses
                        @else
                            Sin caducidad
                        @endif
                    </li>
                    <li>{{ $plantilla->bonos_count }} vendido(s)</li>
                </ul>

                <div class="tarjeta-bono__acciones">
                    <a href="{{ route('panel.bonos.editar', $plantilla) }}"
                       class="boton boton--secundario boton--pequeno">Editar</a>

                    <form method="POST" action="{{ route('panel.bonos.borrar', $plantilla) }}"
                          onsubmit="return confirm('¿Eliminar este bono del catálogo?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="boton boton--secundario boton--pequeno">Borrar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/bonos.css') }}?v=14">
@endpush

@endsection
