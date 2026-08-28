@extends('web.base')

@section('titulo', $producto->nombre)
@section('descripcion', $producto->reclamo)

@section('contenido')

<section class="portada-producto" style="--color: {{ $producto->color }}">
    <div class="contenedor">
        <p class="portada__encima">{{ $producto->sector }}</p>

        <h1>{{ $producto->nombre }}</h1>
        <p class="portada-producto__reclamo">{{ $producto->reclamo }}</p>
        <p class="portada-producto__texto">{{ $producto->descripcion }}</p>

        <div class="portada__botones">
            @if ($producto->esSaas())
                <a href="{{ route('web.registro') }}?producto={{ $producto->slug }}"
                   class="boton boton--grande boton--marca">
                    Empezar a usarlo
                </a>
            @elseif ($producto->tieneDescarga())
                @auth('cuenta')
                    <a href="{{ route('web.area.descargas') }}"
                       class="boton boton--grande boton--marca">
                        Descargar
                    </a>
                @else
                    <a href="{{ route('web.registro') }}?producto={{ $producto->slug }}"
                       class="boton boton--grande boton--marca">
                        Crear cuenta y descargar
                    </a>
                @endauth
            @endif

            <a href="{{ route('web.contacto') }}" class="boton boton--grande boton--claro">
                Preguntar
            </a>
        </div>

        @if ($producto->precio_nota)
            <p class="portada__pie">{{ $producto->precio_nota }}</p>
        @endif
    </div>
</section>

<section class="seccion">
    <div class="contenedor contenedor--estrecho">
        <h2>Qué incluye</h2>

        <ul class="lista-caracteristicas">
            @foreach ($producto->caracteristicas ?? [] as $caracteristica)
                <li>{{ $caracteristica }}</li>
            @endforeach
        </ul>

        @if ($producto->esSaas())
            <div class="aviso-saas">
                <h3>Se usa desde internet</h3>
                <p>
                    No hay que instalar nada. Al crear tu cuenta eliges la
                    dirección de tu salón —por ejemplo
                    <code>tusalon.climacopos.com</code>— y entras desde
                    cualquier ordenador, tablet o móvil.
                </p>
                <p>
                    Tus clientas reservan desde esa misma dirección, sin
                    llamarte ni esperar a que abras.
                </p>
            </div>
        @else
            <div class="aviso-saas">
                <h3>Se instala en tu equipo</h3>
                <p>
                    Funciona sin internet y los datos se quedan en tu
                    ordenador. Necesitas Windows 10 o superior.
                </p>
                @if ($producto->versionActual)
                    <p>
                        Versión actual: <strong>{{ $producto->versionActual->version }}</strong>,
                        publicada el {{ $producto->versionActual->publicada_el->format('d/m/Y') }}.
                    </p>
                @endif
            </div>
        @endif
    </div>
</section>

<section class="seccion seccion--alterna">
    <div class="contenedor">
        <header class="seccion__cabecera">
            <h2>Otras soluciones</h2>
        </header>

        <div class="rejilla-productos">
            @foreach ($otros as $otro)
                <article class="tarjeta-producto tarjeta-producto--compacta"
                         style="--color: {{ $otro->color }}">
                    <span class="tarjeta-producto__sector">{{ $otro->sector }}</span>
                    <h3>{{ $otro->nombre }}</h3>
                    <p class="tarjeta-producto__texto">{{ $otro->reclamo }}</p>
                    <a href="{{ route('web.producto', $otro->slug) }}" class="boton boton--producto">
                        Ver más
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>

@endsection
