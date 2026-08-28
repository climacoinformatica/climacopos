@extends('correos.base')

@section('contenido')

<h1>Ya casi está</h1>

<p>Hola {{ explode(' ', $cuenta->nombre)[0] }},</p>

<p>
    Confirma tu correo y tendrás la cuenta lista para descargar nuestros
    programas y, si lo necesitas, crear tu salón en la nube.
</p>

<p style="text-align:center;margin:32px 0">
    <a href="{{ $enlace }}" class="boton">Confirmar mi cuenta</a>
</p>

<p style="font-size:13px;color:#64748b">
    Si el botón no funciona, copia esta dirección en tu navegador:<br>
    <span style="word-break:break-all">{{ $enlace }}</span>
</p>

<hr>

<p style="font-size:13px;color:#64748b">
    Si no has sido tú quien ha creado esta cuenta, ignora este correo:
    sin confirmar, no se activa nada.
</p>

@endsection
