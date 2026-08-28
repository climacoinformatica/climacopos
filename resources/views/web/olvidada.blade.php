@extends('web.base')

@section('titulo', 'He olvidado mi contraseña')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <h1>¿Has olvidado tu contraseña?</h1>
        <p class="subtitulo">
            Escribe tu correo y te mandamos un enlace para entrar y ponerte
            una nueva.
        </p>

        @if ($errors->any())
            <div class="mensaje mensaje--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.olvidada.enviar') }}" class="formulario">
            @csrf

            <div class="campo">
                <label for="email">Tu correo electrónico</label>
                <input type="email" id="email" name="email" required autofocus
                       value="{{ old('email') }}">
                <small>El mismo con el que creaste la cuenta.</small>
            </div>

            <button type="submit" class="boton boton--grande boton--marca boton--ancho">
                Enviarme el enlace
            </button>
        </form>

        <p class="pie-formulario">
            <a href="{{ route('web.acceso') }}">Volver a entrar</a>
        </p>
    </div>
</section>

@endsection
