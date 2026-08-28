@extends('correo.plataforma.base')

@section('asunto', 'Confirma tu cuenta')

@section('contenido')

<h1 style="margin:0 0 16px;font-size:21px;color:#0f172a;">Bienvenido a CLIMACO POS</h1>

<p style="margin:0 0 14px;">Hola {{ explode(' ', $cuenta->nombre)[0] }},</p>

<p style="margin:0 0 14px;">
    Ya casi está. Solo falta que confirmes que este correo es tuyo:
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto;">
<tr><td align="center" style="border-radius:8px;background:#4f46e5;">
    <a href="{{ $enlace }}"
       style="display:inline-block;padding:13px 28px;color:#ffffff;
              text-decoration:none;font-weight:600;font-size:15px;">
        Confirmar mi cuenta
    </a>
</td></tr>
</table>

<p style="margin:0 0 14px;font-size:13px;color:#64748b;">
    Si el botón no funciona, copia esta dirección en tu navegador:<br>
    <span style="word-break:break-all;">{{ $enlace }}</span>
</p>

<hr style="border:0;border-top:1px solid #e2e8f0;margin:22px 0;">

<p style="margin:0 0 10px;">
    Una vez dentro podrás descargar el programa que necesites y, si es
    CLIMACO POS Beauty, crear tu salón en un minuto.
</p>

<p style="margin:0;font-size:13px;color:#64748b;">
    Si no has creado ninguna cuenta, ignora este correo.
</p>

@endsection
