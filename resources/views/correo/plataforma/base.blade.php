{{--
    Plantilla base de los correos DE LA PLATAFORMA.

    Distinta de correo/base.blade.php a proposito: aquella lleva la
    cabecera del salon, porque son correos que la peluqueria manda a sus
    clientas. Estos los mandamos nosotros, asi que la marca es CLIMACO POS.

    Y hay una razon practica: aquella espera una variable $empresa que en
    los correos a una cuenta no existe, y sin ella el correo salia sin
    cabecera ni pie.

    Tablas y estilos en linea a proposito: Outlook y Gmail ignoran buena
    parte del CSS moderno, y flexbox o grid se rompen sin aviso.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('asunto', 'CLIMACO POS')</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">
<tr><td align="center">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">

        {{-- Cabecera --}}
        <tr>
            <td style="background:#1e293b;padding:22px 28px;">
                <p style="margin:0;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:.5px;">
                    CLIMACO POS
                </p>
                <p style="margin:4px 0 0;color:#94a3b8;font-size:12px;">
                    Software de gestión hecho en Canarias
                </p>
            </td>
        </tr>

        {{-- Contenido --}}
        <tr>
            <td style="padding:28px;color:#0f172a;font-size:15px;line-height:1.65;">
                @yield('contenido')
            </td>
        </tr>

        {{-- Pie --}}
        <tr>
            <td style="background:#f8fafc;padding:18px 28px;border-top:1px solid #e2e8f0;">
                <p style="margin:0 0 6px;color:#64748b;font-size:12px;">
                    <strong>Climaco Informática</strong> · La Palma, Islas Canarias
                </p>

                <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">
                    Si necesitas algo, responde a este mensaje: lo leemos nosotros,
                    no un contestador automático.
                </p>
            </td>
        </tr>
    </table>

    {{-- Enlaces bajo la tarjeta --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
    <tr><td align="center" style="padding:16px 8px;">
        <p style="margin:0;color:#94a3b8;font-size:11px;">
            <a href="https://climacopos.com" style="color:#64748b;text-decoration:none;">climacopos.com</a>
        </p>
    </td></tr>
    </table>

</td></tr>
</table>

</body>
</html>
