@extends('correo.base')

@section('contenido')
    <p style="margin:0 0 8px;color:#0f172a;font-size:20px;font-weight:700;">
        Hemos recibido tu solicitud
    </p>

    <p style="margin:0 0 4px;color:#334155;font-size:15px;line-height:1.6;">
        Hola {{ $reserva->cliente_nombre }}. Tu cita todavía <strong>no está confirmada</strong>:
        la revisamos y te avisamos enseguida.
    </p>

    @include('correo._cita')

    <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
        Recibirás otro correo cuando la confirmemos. Si es urgente, llámanos.
    </p>
@endsection
