@extends('panel.base')

@section('titulo', 'Fichar entrada')

@section('contenido')
{{--
    Pantalla intermedia entre el PIN y el destino.

    Solo aparece cuando el usuario ficha jornada y esta fuera. Si vuelve
    al selector durante el turno (por ejemplo tras cobrar), no se le
    pregunta nada: seria un estorbo en cada venta.

    Las dos salidas llevan al mismo sitio, asi que nadie se queda
    atrapado aqui por no querer fichar.
--}}
<div class="fichaje-entrada">
    <h1>Hola, {{ $usuario->alias ?: $usuario->nombre }}</h1>
    <p class="fichaje-entrada__hora">Son las {{ now()->format('H:i') }}</p>

    <p class="fichaje-entrada__pregunta">¿Quieres fichar la entrada?</p>

    <div class="fichaje-entrada__botones">
        <form method="POST" action="{{ route('panel.selector.entrada.registrar') }}">
            @csrf
            <input type="hidden" name="fichar" value="1">
            <button type="submit" class="boton boton--ancho">Sí, fichar entrada</button>
        </form>

        <form method="POST" action="{{ route('panel.selector.entrada.registrar') }}">
            @csrf
            <input type="hidden" name="fichar" value="0">
            <button type="submit" class="boton boton--secundario boton--ancho">Ahora no</button>
        </form>
    </div>

    <p class="fichaje-entrada__pie">
        Si eliges «Ahora no», puedes fichar después desde el menú Fichar.
    </p>
</div>
@endsection
