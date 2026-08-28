@extends('correo.base')

@section('contenido')
    <p style="margin:0 0 8px;color:#0f172a;font-size:20px;font-weight:700;">
        Tu cita es mañana
    </p>

    <p style="margin:0 0 4px;color:#334155;font-size:15px;line-height:1.6;">
        Hola {{ $reserva->cliente_nombre }}, un recordatorio rápido.
    </p>

    @include('correo._cita')

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
        <tr>
            <td style="background:#6366f1;border-radius:8px;">
                <a href="{{ $empresa->urlPortal() }}/cita/{{ $reserva->codigo }}"
                   style="display:inline-block;padding:12px 24px;color:#ffffff;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    Ver mi cita
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
        Si te ha surgido algo, avísanos cuanto antes desde ese enlace.
        Con tiempo podemos dar el hueco a otra persona.
    </p>
@endsection
