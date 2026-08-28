@extends('web.base')

@section('titulo', 'Confirma tu correo')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario texto-centrado">
        <div class="icono-grande">✉</div>

        <h1>Revisa tu correo</h1>

        <p class="subtitulo">
            Hemos enviado un enlace de confirmación
            @if ($email)
                a <strong>{{ $email }}</strong>
            @endif
            . Ábrelo y tu cuenta quedará lista.
        </p>

        <div class="aviso-saas texto-izquierda">
            <h3>¿No te llega?</h3>
            <p>
                Mira en la carpeta de spam: los correos automáticos acaban
                ahí más de lo que quisiéramos. Si no aparece en unos
                minutos, podemos reenviarlo.
            </p>

            <form method="POST" action="{{ route('web.registro.reenviar') }}" class="formulario-linea">
                @csrf
                <input type="email" name="email" required placeholder="tu@correo.com"
                       value="{{ $email }}">
                <button type="submit" class="boton boton--marca">Reenviar</button>
            </form>
        </div>
    </div>
</section>

@endsection
