@extends('web.base')

@section('titulo', 'Descargas')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--estrecho">
        <h1>Descargas</h1>
        <p class="subtitulo">
            La versión actual y las anteriores, por si necesitas volver atrás.
        </p>

        @foreach ($productos as $producto)
            <div class="bloque-area" style="--color: {{ $producto->color }}">
                <h2>{{ $producto->nombre }}</h2>

                @if ($producto->versiones->isEmpty())
                    <p class="nota-pequena">Todavía no hay versiones publicadas.</p>
                @else
                    @foreach ($producto->versiones as $version)
                        <div class="fila-version {{ $version->es_actual ? 'fila-version--actual' : '' }}">
                            <div>
                                <strong>
                                    {{ $version->version }}
                                    @if ($version->es_actual)
                                        <span class="etiqueta-actual">actual</span>
                                    @endif
                                </strong>

                                <p class="nota-pequena">
                                    {{ $version->publicada_el->format('d/m/Y') }}
                                    · {{ $version->tamanoLegible() }}
                                </p>

                                @if ($version->novedades)
                                    <details>
                                        <summary>Novedades</summary>
                                        <div class="novedades">{!! nl2br(e($version->novedades)) !!}</div>
                                    </details>
                                @endif
                            </div>

                            <a href="{{ route('web.area.descargar', $version) }}"
                               class="boton {{ $version->es_actual ? 'boton--producto' : 'boton--claro' }}">
                                Descargar
                            </a>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach

        <p class="pie-formulario">
            <a href="{{ route('web.area') }}">Volver a mi cuenta</a>
        </p>
    </div>
</section>

@endsection
