@extends('web.base')

@section('titulo', 'Software de gestión para tu negocio')

@section('contenido')

{{-- ================= Portada ================= --}}
<section class="portada">
    <div class="contenedor">
        <p class="portada__encima">Hecho en Canarias</p>

        <h1 class="portada__titulo">
            El software que entiende<br>
            <em>cómo funciona tu negocio</em>
        </h1>

        <p class="portada__texto">
            Tres soluciones para tres sectores que no se parecen en nada.
            Ni un programa genérico con el nombre cambiado, ni una hoja de
            cálculo con botones.
        </p>

        <div class="portada__botones">
            <a href="#soluciones" class="boton boton--grande boton--marca">Ver las soluciones</a>
            <a href="{{ route('web.contacto') }}" class="boton boton--grande boton--claro">Hablar con nosotros</a>
        </div>

        <p class="portada__pie">
            Cumplen <strong>VERI*FACTU</strong> · Soporte directo, sin centralita
        </p>
    </div>
</section>

{{-- ================= Soluciones ================= --}}
<section class="seccion" id="soluciones">
    <div class="contenedor">
        <header class="seccion__cabecera">
            <h2>Elige tu sector</h2>
            <p>
                Cada negocio tiene su ritmo. Un restaurante en pleno servicio
                no necesita lo mismo que un salón con las citas contadas.
            </p>
        </header>

        <div class="rejilla-productos">
            @foreach ($productos as $producto)
                <article class="tarjeta-producto" style="--color: {{ $producto->color }}">
                    <div class="tarjeta-producto__cabecera">
                        <span class="tarjeta-producto__sector">{{ $producto->sector }}</span>
                        <h3>{{ $producto->nombre }}</h3>
                        <p class="tarjeta-producto__reclamo">{{ $producto->reclamo }}</p>
                    </div>

                    <p class="tarjeta-producto__texto">{{ $producto->descripcion }}</p>

                    <ul class="lista-marcada">
                        @foreach (array_slice($producto->caracteristicas ?? [], 0, 4) as $caracteristica)
                            <li>{{ $caracteristica }}</li>
                        @endforeach
                    </ul>

                    <div class="tarjeta-producto__pie">
                        <span class="etiqueta-modalidad">
                            {{ $producto->etiquetaModalidad() }}
                        </span>

                        <a href="{{ route('web.producto', $producto->slug) }}" class="boton boton--producto">
                            Ver más
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ================= Por qué ================= --}}
<section class="seccion seccion--alterna">
    <div class="contenedor">
        <header class="seccion__cabecera">
            <h2>Por qué CLIMACO</h2>
        </header>

        <div class="rejilla-razones">
            <div class="razon">
                <h3>Hablas con quien lo programa</h3>
                <p>
                    No hay centralita ni tickets que nadie lee. Si algo falla,
                    lo cuentas y se arregla. Si falta algo que necesitas, se
                    valora de verdad.
                </p>
            </div>

            <div class="razon">
                <h3>Al día con Hacienda</h3>
                <p>
                    Los tres cumplen VERI*FACTU: huella encadenada, código QR
                    en el ticket y envío a la AEAT. Sin que tengas que
                    entender nada de eso.
                </p>
            </div>

            <div class="razon">
                <h3>Pensado para Canarias</h3>
                <p>
                    IGIC en lugar de IVA, con sus tipos y sus recargos.
                    Parece un detalle hasta que usas un programa peninsular
                    y no cuadra nada.
                </p>
            </div>

            <div class="razon">
                <h3>Tus datos son tuyos</h3>
                <p>
                    En las versiones instalables, los datos están en tu
                    equipo. En la de peluquerías, en servidores europeos y
                    con copia diaria.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ================= Llamada final ================= --}}
<section class="cierre">
    <div class="contenedor">
        <h2>Crea tu cuenta y descarga</h2>
        <p>
            Es gratis y te lleva un minuto. Desde tu área podrás descargar
            los programas, ver las novedades de cada versión y gestionar tu
            salón si eliges la solución de peluquerías.
        </p>

        <a href="{{ route('web.registro') }}" class="boton boton--grande boton--marca">
            Crear cuenta gratis
        </a>
    </div>
</section>

@endsection
