@extends('correo.plataforma.base')

@section('asunto', 'Recupera tu acceso')

@section('contenido')

<h1 style="margin:0 0 16px;font-size:21px;color:#0f172a;">Recupera tu acceso</h1>

<p style="margin:0 0 14px;">Hola {{ explode(' ', $cuenta->nombre)[0] }},</p>

<p style="margin:0 0 14px;">
    Has pedido volver a entrar en tu cuenta de CLIMACO POS. Pulsa el botón
    y podrás poner una contraseña nueva.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto;">
<tr><td align="center" style="border-radius:8px;background:#4f46e5;">
    <a href="{{ $enlace }}"
       style="display:inline-block;padding:13px 28px;color:#ffffff;
              text-decoration:none;font-weight:600;font-size:15px;">
        Elegir nueva contraseña
    </a>
</td></tr>
</table>

<p style="margin:0 0 14px;font-size:13px;color:#64748b;">
    Si el botón no funciona, copia esta dirección en tu navegador:<br>
    <span style="word-break:break-all;">{{ $enlace }}</span>
</p>

<hr style="border:0;border-top:1px solid #e2e8f0;margin:22px 0;">

<p style="margin:0 0 10px;font-size:13px;color:#64748b;">
    <strong>El enlace vale una hora.</strong> Pasado ese tiempo tendrás que
    pedir otro, que es cuestión de un minuto.
</p>

<p style="margin:0;font-size:13px;color:#64748b;">
    Si no has sido tú quien lo ha pedido, ignora este correo: tu contraseña
    sigue siendo la de siempre y nadie ha entrado en tu cuenta.
</p>

@endsection
