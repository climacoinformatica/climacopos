@extends('correo.plataforma.base')

@section('contenido')
    <p style="margin:0 0 12px;color:#0f172a;font-size:19px;font-weight:700;">
        No hemos podido cobrar tu cuota
    </p>

    <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">
        Hola. El cobro de la cuota de <strong>{{ $empresa->nombre_comercial }}</strong>
        no ha salido adelante. Suele ser algo sencillo: una tarjeta caducada
        o un límite de la entidad.
    </p>

    <p style="margin:0 0 20px;padding:14px 18px;background:#ecfdf5;border-radius:8px;
              color:#065f46;font-size:14px;line-height:1.6;">
        <strong>No te preocupes: todo sigue funcionando con normalidad.</strong>
        Puedes seguir vendiendo, reservando y sacando informes como siempre.
    </p>

    <p style="margin:0 0 20px;color:#334155;font-size:14px;line-height:1.6;">
        Solo te pedimos que revises tu forma de pago cuando puedas. Si el
        siguiente intento tampoco funciona, la cuenta pasaría a modo solo lectura.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background:#6366f1;border-radius:8px;">
                <a href="{{ $empresa->urlPortal() }}/panel/suscripcion"
                   style="display:inline-block;padding:12px 24px;color:#fff;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    Revisar mi forma de pago
                </a>
            </td>
        </tr>
    </table>
@endsection
