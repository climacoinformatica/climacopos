@extends('web.base')

@section('titulo', 'Crear cuenta')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <h1>Crear cuenta</h1>
        <p class="subtitulo">
            Una sola cuenta para las tres soluciones. Es gratis y no
            compromete a nada.
        </p>

        @if ($errors->any())
            <div class="mensaje mensaje--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.registro.enviar') }}" class="formulario">
            @csrf

            <div class="campo">
                <label for="nombre">Tu nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="120"
                       value="{{ old('nombre') }}" autofocus>
            </div>

            <div class="campo">
                <label for="email">Correo electrónico *</label>
                <input type="email" id="email" name="email" required maxlength="160"
                       value="{{ old('email') }}">
                <small>Te enviaremos un enlace para confirmarlo.</small>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="password">Contraseña *</label>
                    <input type="password" id="password" name="password" required
                           minlength="8" autocomplete="new-password">
                    <small>Al menos ocho caracteres.</small>
                </div>

                <div class="campo">
                    <label for="password_confirmation">Repítela *</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           required autocomplete="new-password">
                </div>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="empresa">Nombre del negocio</label>
                    <input type="text" id="empresa" name="empresa" maxlength="120"
                           value="{{ old('empresa') }}">
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="30"
                           value="{{ old('telefono') }}">
                </div>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="sector">Sector</label>
                    <select id="sector" name="sector">
                        <option value="">— Elige —</option>
                        @foreach (['Hostelería', 'Deporte', 'Belleza', 'Otro'] as $sector)
                            <option value="{{ $sector }}"
                                    @selected(old('sector', request('producto') === 'restaurant' ? 'Hostelería'
                                        : (request('producto') === 'gym' ? 'Deporte'
                                        : (request('producto') === 'beauty' ? 'Belleza' : ''))) === $sector)>
                                {{ $sector }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="provincia">Provincia</label>
                    <input type="text" id="provincia" name="provincia" maxlength="60"
                           value="{{ old('provincia') }}">
                </div>
            </div>

            <label class="casilla">
                <input type="checkbox" name="acepta" value="1" required @checked(old('acepta'))>
                <span>
                    He leído y acepto las
                    <a href="{{ route('web.legal', 'condiciones') }}" target="_blank">condiciones</a>
                    y la
                    <a href="{{ route('web.legal', 'privacidad') }}" target="_blank">política de privacidad</a>. *
                </span>
            </label>

            <label class="casilla">
                <input type="checkbox" name="novedades" value="1" @checked(old('novedades'))>
                <span>
                    Quiero recibir avisos de nuevas versiones y mejoras.
                    Nada de publicidad de terceros.
                </span>
            </label>

            <button type="submit" class="boton boton--grande boton--marca boton--ancho">
                Crear cuenta
            </button>
        </form>

        <p class="pie-formulario">
            ¿Ya tienes cuenta? <a href="{{ route('web.acceso') }}">Entra aquí</a>
        </p>
    </div>
</section>

@endsection
