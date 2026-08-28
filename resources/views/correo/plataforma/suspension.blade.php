@extends('correo.plataforma.base')

@section('contenido')
    <p style="margin:0 0 12px;color:#0f172a;font-size:19px;font-weight:700;">
        Tu cuenta pasará a solo lectura
    </p>

    <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">
        Hola. Es el segundo intento de cobro que no sale adelante en
        <strong>{{ $empresa->nombre_comercial }}</strong>.
    </p>

    <p style="margin:0 0 20px;padding:14px 18px;background:#fef3c7;border-radius:8px;
              color:#78350f;font-size:14px;line-height:1.6;">
        <strong>Tienes hasta esta madrugada.</strong>
        No cortamos el servicio en mitad de una jornada de trabajo: el cambio
        entra a las {{ $empresa->suspension_efectiva_en?->format('H:i') }}
        de la madrugada. Si regularizas antes, no notarás nada.
    </p>

    <p style="margin:0 0 8px;color:#334155;font-size:14px;line-height:1.6;">
        A partir de ese momento <strong>podrás seguir entrando</strong> y consultar
        tu agenda y tus clientes, para poder atender a quien ya tenga cita. Lo que
        no podrás hacer es cobrar, crear reservas ni sacar informes.
    </p>

    <p style="margin:0 0 20px;color:#334155;font-size:14px;line-height:1.6;">
        Todo vuelve a la normalidad en cuanto se cobre la cuota.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background:#6366f1;border-radius:8px;">
                <a href="{{ $empresa->urlPortal() }}/panel/suscripcion"
                   style="display:inline-block;padding:12px 24px;color:#fff;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    Regularizar ahora
                </a>
            </td>
        </tr>
    </table>
@endsection
