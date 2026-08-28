@extends('correo.base')

@section('contenido')
    <p style="margin:0 0 8px;color:#0f172a;font-size:20px;font-weight:700;">
        Tu cita ha sido cancelada
    </p>

    <p style="margin:0 0 4px;color:#334155;font-size:15px;line-height:1.6;">
        Hola {{ $reserva->cliente_nombre }}, hemos cancelado esta cita.
    </p>

    @include('correo._cita')

    @if ($motivo)
        <p style="margin:0 0 16px;padding:12px 16px;background:#fef3c7;border-radius:8px;
                  color:#78350f;font-size:14px;">
            {{ $motivo }}
        </p>
    @endif

    @if ($reserva->importe_pagado > 0)
        <p style="margin:0 0 16px;color:#334155;font-size:14px;line-height:1.6;">
            Te devolvemos el importe que habías pagado. Puede tardar unos días
            en aparecer en tu banco, según la entidad.
        </p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 0;">
        <tr>
            <td style="background:#6366f1;border-radius:8px;">
                <a href="{{ $empresa->urlPortal() }}"
                   style="display:inline-block;padding:12px 24px;color:#ffffff;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    Pedir otra cita
                </a>
            </td>
        </tr>
    </table>
@endsection
