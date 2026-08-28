@extends('correo.base')

@section('contenido')
    <p style="margin:0 0 8px;color:#0f172a;font-size:20px;font-weight:700;">
        Pago recibido
    </p>

    <p style="margin:0 0 4px;color:#334155;font-size:15px;line-height:1.6;">
        Hemos recibido tu pago de
        <strong>{{ number_format($pago->importe, 2, ',', '.') }} €</strong>.
        @if ($pago->tipo === 'FIANZA')
            Se descuenta del total el día de tu cita.
        @endif
    </p>

    @if ($reserva)
        @include('correo._cita')
    @endif

    <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
        Referencia del pago: {{ $pago->referencia }}
    </p>
@endsection
