@extends('web.base')

@section('titulo', 'Contacto')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--estrecho">
        <h1>Contacto</h1>
        <p class="subtitulo">
            Escribe y contesta quien programa el software. No hay centralita
            ni formularios que se pierden.
        </p>

        <div class="datos-contacto">
            <div class="dato-contacto">
                <h3>Correo</h3>
                <a href="mailto:info@climacopos.com">info@climacopos.com</a>
            </div>

            <div class="dato-contacto">
                <h3>Dónde estamos</h3>
                <p>La Palma, Islas Canarias</p>
            </div>

            <div class="dato-contacto">
                <h3>Soporte</h3>
                <p>
                    Si ya eres cliente, escribe desde el correo con el que
                    te registraste: así vemos tu cuenta y tus versiones
                    directamente.
                </p>
            </div>
        </div>
    </div>
</section>

@endsection
