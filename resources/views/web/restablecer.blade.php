@extends('web.base')

@section('titulo', 'Nueva contraseña')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <h1>Elige tu nueva contraseña</h1>
        <p class="subtitulo">
            Al guardarla entrarás directamente en tu cuenta.
        </p>

        @if ($errors->any())
            <div class="mensaje mensaje--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.restablecer.guardar') }}" class="formulario">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required
                       value="{{ old('email', $email) }}">
            </div>

            <div class="campo">
                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" required
                       minlength="8" autocomplete="new-password" autofocus>
                <small>Al menos ocho caracteres.</small>
            </div>

            <div class="campo">
                <label for="password_confirmation">Repítela</label>
                <input type="password" id="password_confirmation"
                       name="password_confirmation" required
                       autocomplete="new-password">
            </div>

            <button type="submit" class="boton boton--grande boton--marca boton--ancho">
                Guardar y entrar
            </button>
        </form>

        <p class="pie-formulario">
            El enlace vale una hora. Si ha caducado,
            <a href="{{ route('web.olvidada') }}">pide otro</a>.
        </p>
    </div>
</section>

@endsection
