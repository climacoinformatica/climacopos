@extends('correo.base')

@section('contenido')
    <p style="margin:0 0 8px;color:#0f172a;font-size:20px;font-weight:700;">
        Te hemos devuelto el importe
    </p>

    <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">
        Hemos devuelto <strong>{{ number_format($pago->devuelto_importe, 2, ',', '.') }} €</strong>
        a la misma tarjeta con la que pagaste.
    </p>

    <p style="margin:0 0 16px;padding:12px 16px;background:#f8fafc;border-radius:8px;
              color:#334155;font-size:14px;line-height:1.6;">
        Según tu banco, puede tardar entre 3 y 10 días en aparecer en tu cuenta.
        No es algo que podamos acelerar desde aquí.
    </p>

    <p style="margin:0;color:#64748b;font-size:13px;">
        Referencia: {{ $pago->referencia }}
    </p>
@endsection
