@extends('panel.base')

@section('titulo', 'Confirma tu contraseña')

@section('contenido')
<div class="formulario">
    <h1 style="text-align:center;margin-bottom:.5rem;font-size:1.25rem">Confirma tu contraseña</h1>
    <p style="text-align:center;color:var(--suave);font-size:.85rem;margin-bottom:1.5rem">
        {{ $usuario->nombre }}<br>
        Esta acción es sensible y el PIN no basta.
    </p>

    @if ($errors->any())
        <p class="aviso aviso--error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('panel.reautenticar.post') }}">
        @csrf

        <div class="campo">
            <label for="password">Contraseña</label>

            {{--
                data-teclado="pin" fuerza el teclado numerico.

                Dentro del panel las contrasenas son numericas: se teclean
                con prisa en el mostrador, muchas veces en una tablet, y un
                teclado alfanumerico en pantalla es inservible para eso.

                inputmode="numeric" hace lo mismo con el teclado del
                sistema en moviles.
            --}}
            <input type="password" id="password" name="password"
                   required autofocus autocomplete="current-password"
                   inputmode="numeric"
                   data-teclado="pin"
                   maxlength="12">
        </div>

        <button type="submit" class="boton">Continuar</button>
    </form>

    <form method="POST" action="{{ route('panel.salir') }}" style="margin-top:.75rem">
        @csrf
        <button type="submit" class="boton boton--secundario">Cambiar de usuario</button>
    </form>

    <p style="color:var(--suave);font-size:.75rem;margin-top:1.5rem;text-align:center">
        Válido durante {{ \App\Models\Usuario::MINUTOS_REAUTENTICACION }} minutos.
    </p>
</div>
@endsection
