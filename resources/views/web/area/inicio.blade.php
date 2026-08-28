@extends('web.base')

@section('titulo', 'Mi cuenta')

@section('contenido')

<section class="seccion">
    <div class="contenedor">
        <div class="area-cabecera">
            <div>
                <h1>Hola, {{ explode(' ', $cuenta->nombre)[0] }}</h1>
                <p class="subtitulo">{{ $cuenta->email }}</p>
            </div>

            <div class="area-cabecera__acciones">
                <a href="{{ route('web.area.perfil') }}" class="boton boton--claro">Mis datos</a>

                <form method="POST" action="{{ route('web.salir') }}">
                    @csrf
                    <button type="submit" class="boton boton--claro">Salir</button>
                </form>
            </div>
        </div>

        {{-- ---------- Mis salones ---------- --}}
        @if ($empresas->isNotEmpty())
            <div class="bloque-area">
                <h2>Mi salón</h2>

                @foreach ($empresas as $empresa)
                    <div class="tarjeta-salon">
                        <div>
                            <strong>{{ $empresa->nombre_comercial }}</strong>
                            <p>{{ $empresa->slug }}.climacopos.com</p>
                        </div>

                        <a href="https://{{ $empresa->slug }}.climacopos.com"
                           class="boton boton--marca" target="_blank" rel="noopener">
                            Entrar
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ---------- Descargas ---------- --}}
        <div class="bloque-area">
            <h2>Descargas</h2>

            <div class="rejilla-descargas">
                @foreach ($productos as $producto)
                    <div class="tarjeta-descarga" style="--color: {{ $producto->color }}">
                        <span class="tarjeta-producto__sector">{{ $producto->sector }}</span>
                        <h3>{{ $producto->nombre }}</h3>

                        @if ($producto->esSaas())
                            <p class="tarjeta-descarga__nota">
                                Se usa desde internet, no hay nada que descargar.
                            </p>

                            @if ($empresas->isEmpty())
                                <a href="{{ route('web.alta') }}" class="boton boton--producto">Crear mi salón</a>
                            @endif

                        @elseif ($producto->versionActual)
                            <p class="tarjeta-descarga__version">
                                Versión {{ $producto->versionActual->version }}
                                · {{ $producto->versionActual->tamanoLegible() }}
                            </p>

                            <a href="{{ route('web.area.descargar', $producto->versionActual) }}"
                               class="boton boton--producto">
                                Descargar
                            </a>
                        @else
                            <p class="tarjeta-descarga__nota">
                                Todavía no hay ninguna versión publicada.
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="pie-formulario">
                <a href="{{ route('web.area.descargas') }}">Ver todas las versiones</a>
            </p>
        </div>

        {{-- ---------- Historial ---------- --}}
        @if ($descargas->isNotEmpty())
            <div class="bloque-area">
                <h2>Tus últimas descargas</h2>

                <table class="tabla-simple">
                    <thead>
                        <tr><th>Producto</th><th>Versión</th><th>Cuándo</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($descargas as $descarga)
                        <tr>
                            <td>{{ $descarga->version->producto->nombre }}</td>
                            <td>{{ $descarga->version->version }}</td>
                            <td>{{ $descarga->fecha->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <p class="nota-pequena">
                    Te lo mostramos porque saber qué versión tienes es la primera
                    pregunta cuando algo falla.
                </p>
            </div>
        @endif
    </div>
</section>

@endsection
