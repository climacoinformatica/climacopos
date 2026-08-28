@extends('correo.plataforma.base')

@section('contenido')
    <p style="margin:0 0 12px;color:#0f172a;font-size:19px;font-weight:700;">
        Tus datos se eliminarán pronto
    </p>

    <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">
        Hola. La cuenta de <strong>{{ $empresa->nombre_comercial }}</strong>
        lleva tiempo sin actividad ni pago.
    </p>

    <p style="margin:0 0 20px;padding:14px 18px;background:#fee2e2;border-radius:8px;
              color:#991b1b;font-size:14px;line-height:1.6;">
        <strong>El {{ $empresa->borrar_a_partir_de?->format('d/m/Y') }}</strong>
        eliminaremos de forma definitiva tu agenda, tus clientes, tu catálogo
        y tu historial de ventas. No hay vuelta atrás.
    </p>

    <p style="margin:0 0 20px;color:#334155;font-size:14px;line-height:1.6;">
        Si quieres conservar tus datos tienes dos opciones: reactivar la cuenta,
        o escribirnos antes de esa fecha y te enviamos una copia de tu información
        para que la guardes.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background:#6366f1;border-radius:8px;">
                <a href="{{ $empresa->urlPortal() }}/panel/suscripcion"
                   style="display:inline-block;padding:12px 24px;color:#fff;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    Reactivar mi cuenta
                </a>
            </td>
        </tr>
    </table>
@endsection
