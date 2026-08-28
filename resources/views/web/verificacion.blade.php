@extends('web.base')

@section('titulo', 'Verificación')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario texto-centrado">
        <div class="icono-grande">⚠</div>

        <h1>Ese enlace ya no vale</h1>

        <p class="subtitulo">
            Puede que ya lo hayas usado y tu cuenta esté verificada, o que
            el enlace sea antiguo. Prueba a entrar directamente.
        </p>

        <a href="{{ route('web.acceso') }}" class="boton boton--grande boton--marca">
            Entrar
        </a>

        <p class="pie-formulario" style="margin-top:2rem">
            Si no funciona, podemos
            <a href="{{ route('web.registro.enviado') }}">reenviarte la verificación</a>.
        </p>
    </div>
</section>

@endsection
