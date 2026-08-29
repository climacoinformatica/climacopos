@extends('correo.plataforma.base')

@section('asunto', $titulo)

@section('contenido')

<h1 style="margin:0 0 16px;font-size:21px;color:#0f172a;">{{ $titulo }}</h1>

<p style="margin:0 0 14px;">
    Adjunto va el {{ mb_strtolower($titulo) }} {{ $descripcion }}
    de <strong>{{ $salon }}</strong>.
</p>

<p style="margin:0 0 14px;font-size:13px;color:#64748b;">
    El documento va en PDF, listo para archivar o reenviar a tu asesoría.
</p>

<hr style="border:0;border-top:1px solid #e2e8f0;margin:22px 0;">

<p style="margin:0;font-size:13px;color:#64748b;">
    Si no esperabas este correo, avísanos: alguien con acceso a tu panel
    lo ha enviado.
</p>

@endsection
