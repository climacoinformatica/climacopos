{{--
    Plantilla base de los correos.

    Tablas y estilos en línea a propósito: Outlook y Gmail ignoran buena
    parte del CSS moderno, y flexbox o grid se rompen sin aviso. Es feo
    de escribir pero es lo que llega bien a todas partes.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('asunto', $empresa->nombre_comercial)</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">
<tr><td align="center">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">

        {{-- Cabecera --}}
        <tr>
            <td style="background:#1e293b;padding:22px 28px;">
                <p style="margin:0;color:#ffffff;font-size:18px;font-weight:700;">
                    {{ $empresa->nombre_comercial }}
                </p>
                @if ($empresa->telefono)
                    <p style="margin:4px 0 0;color:#94a3b8;font-size:13px;">
                        {{ $empresa->telefono }}
                    </p>
                @endif
            </td>
        </tr>

        {{-- Contenido --}}
        <tr>
            <td style="padding:28px;">
                @yield('contenido')
            </td>
        </tr>

        {{-- Pie --}}
        <tr>
            <td style="background:#f8fafc;padding:18px 28px;border-top:1px solid #e2e8f0;">
                @if ($empresa->direccion)
                    <p style="margin:0 0 4px;color:#64748b;font-size:12px;">
                        {{ $empresa->direccion }}{{ $empresa->municipio ? ', ' . $empresa->municipio : '' }}
                    </p>
                @endif

                <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">
                    Este correo se ha enviado automáticamente. Si necesitas algo,
                    responde a este mensaje o llámanos.
                </p>
            </td>
        </tr>
    </table>

</td></tr>
</table>

</body>
</html>
