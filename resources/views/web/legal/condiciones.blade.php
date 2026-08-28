@extends('web.base')

@section('titulo', 'condiciones')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--estrecho">
        <h1>{{ ucfirst(str_replace('-', ' ', 'condiciones')) }}</h1>

        <p class="mensaje mensaje--error">
            <strong>Este texto está sin redactar.</strong>
            No lo publiques así: un aviso legal genérico copiado de internet
            no te protege, y en el caso de la privacidad puede acarrear
            sanción. Encárgaselo a un asesor con los datos reales de la
            actividad.
        </p>

        <p class="subtitulo">
            Datos que tendrá que incluir: titular (Jectán Fco. Acosta Sánchez),
            NIF, domicilio, correo de contacto, y en el caso de privacidad,
            qué datos se recogen, con qué base legal, cuánto se conservan y
            cómo ejercer los derechos.
        </p>
    </div>
</section>

@endsection
