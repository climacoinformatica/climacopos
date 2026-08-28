@extends('correo.plataforma.base')

@section('contenido')
    <p style="margin:0 0 12px;color:#0f172a;font-size:19px;font-weight:700;">
        @if ($dias <= 1)
            Tu prueba termina mañana
        @else
            Te quedan {{ $dias }} días de prueba
        @endif
    </p>

    <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">
        Hola. La prueba de <strong>{{ $empresa->nombre_comercial }}</strong>
        termina el {{ $empresa->prueba_hasta?->format('d/m/Y') }}.
    </p>

    <p style="margin:0 0 20px;color:#334155;font-size:14px;line-height:1.6;">
        Si eliges un plan antes de esa fecha, seguirás trabajando sin
        interrupciones y sin perder nada de lo que hayas configurado.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background:#6366f1;border-radius:8px;">
                <a href="{{ $empresa->urlPortal() }}/panel/suscripcion"
                   style="display:inline-block;padding:12px 24px;color:#fff;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    Ver los planes
                </a>
            </td>
        </tr>
    </table>
@endsection
