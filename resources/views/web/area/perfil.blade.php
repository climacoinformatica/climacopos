@extends('web.base')

@section('titulo', 'Mis datos')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <h1>Mis datos</h1>

        @if ($errors->any())
            <div class="mensaje mensaje--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.area.perfil.guardar') }}" class="formulario">
            @csrf

            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="120"
                       value="{{ old('nombre', $cuenta->nombre) }}">
            </div>

            <div class="campo">
                <label>Correo electrónico</label>
                <input type="email" value="{{ $cuenta->email }}" disabled>
                <small>
                    Para cambiarlo, escríbenos: hay que verificar la dirección nueva
                    antes de sustituir la antigua.
                </small>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="empresa">Negocio</label>
                    <input type="text" id="empresa" name="empresa" maxlength="120"
                           value="{{ old('empresa', $cuenta->empresa) }}">
                </div>

                <div class="campo">
                    <label for="nif">NIF</label>
                    <input type="text" id="nif" name="nif" maxlength="20"
                           value="{{ old('nif', $cuenta->nif) }}">
                </div>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="30"
                           value="{{ old('telefono', $cuenta->telefono) }}">
                </div>

                <div class="campo">
                    <label for="provincia">Provincia</label>
                    <input type="text" id="provincia" name="provincia" maxlength="60"
                           value="{{ old('provincia', $cuenta->provincia) }}">
                </div>
            </div>

            <label class="casilla">
                <input type="checkbox" name="acepta_novedades" value="1"
                       @checked(old('acepta_novedades', $cuenta->acepta_novedades))>
                <span>Avisadme de nuevas versiones y mejoras</span>
            </label>

            <button type="submit" class="boton boton--grande boton--marca boton--ancho">
                Guardar
            </button>
        </form>

        {{-- ---------- Contraseña ---------- --}}
        <div class="bloque-contrasena">
            <h2>Cambiar la contraseña</h2>

            @if (session('exito'))
                <p class="mensaje mensaje--ok">{{ session('exito') }}</p>
            @endif

            @if (session('error'))
                <p class="mensaje mensaje--error">{{ session('error') }}</p>
            @endif

            <form method="POST" action="{{ route('web.area.contrasena') }}" class="formulario">
                @csrf

                <div class="campo">
                    <label for="actual">Tu contraseña actual</label>
                    <input type="password" id="actual" name="actual" required
                           autocomplete="current-password">
                    <small>
                        La pedimos por seguridad, para que nadie pueda cambiarla
                        desde un ordenador que te hayas dejado abierto.
                    </small>
                </div>

                <div class="campo">
                    <label for="password">Nueva contraseña</label>
                    <input type="password" id="password" name="password" required
                           minlength="8" autocomplete="new-password">
                    <small>Al menos ocho caracteres.</small>
                </div>

                <div class="campo">
                    <label for="password_confirmation">Repítela</label>
                    <input type="password" id="password_confirmation"
                           name="password_confirmation" required
                           autocomplete="new-password">
                </div>

                <button type="submit" class="boton boton--marca">
                    Cambiar contraseña
                </button>
            </form>
        </div>

        <p class="pie-formulario">
            <a href="{{ route('web.area') }}">Volver a mi cuenta</a>
        </p>
    </div>
</section>

@endsection
