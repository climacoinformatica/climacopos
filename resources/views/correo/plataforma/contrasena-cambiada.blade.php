@extends('correo.plataforma.base')

@section('asunto', 'Tu contraseña ha cambiado')

@section('contenido')

<h1 style="margin:0 0 16px;font-size:21px;color:#0f172a;">Tu contraseña ha cambiado</h1>

<p style="margin:0 0 14px;">Hola {{ explode(' ', $cuenta->nombre)[0] }},</p>

<p style="margin:0 0 14px;">
    Te avisamos de que la contraseña de tu cuenta de CLIMACO POS acaba de
    cambiar, el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}.
</p>

<p style="margin:0 0 14px;">Si has sido tú, no tienes que hacer nada.</p>

<hr style="border:0;border-top:1px solid #e2e8f0;margin:22px 0;">

<p style="margin:0;font-size:13px;color:#64748b;">
    <strong>Si no has sido tú</strong>, escríbenos cuanto antes: alguien
    puede haber entrado en tu cuenta.
</p>

@endsection
