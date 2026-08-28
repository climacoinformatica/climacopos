@extends('web.base')

@section('titulo', 'Entrar')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <h1>Entrar</h1>
        <p class="subtitulo">Accede a tus descargas y a tu salón.</p>

        @if ($errors->any())
            <div class="mensaje mensaje--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.acceso.entrar') }}" class="formulario">
            @csrf

            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required
                       value="{{ old('email') }}" autofocus>
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password">
            </div>

            <div class="fila-acceso">
                <label class="casilla">
                    <input type="checkbox" name="recordar" value="1">
                    <span>Mantener la sesión abierta</span>
                </label>

                <a href="{{ route('web.olvidada') }}" class="enlace-olvidada">
                    He olvidado mi contraseña
                </a>
            </div>

            <button type="submit" class="boton boton--grande boton--marca boton--ancho">
                Entrar
            </button>
        </form>

        <p class="pie-formulario">
            ¿Todavía no tienes cuenta? <a href="{{ route('web.registro') }}">Créala gratis</a>
        </p>
    </div>
</section>

@endsection
