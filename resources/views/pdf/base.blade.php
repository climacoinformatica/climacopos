{{--
    Plantilla base de los PDF.

    Dompdf entiende un subconjunto pequeño de CSS: nada de flexbox ni
    grid, y las tablas son la forma fiable de maquetar. Es como escribir
    HTML de hace veinte años, pero es lo que sale bien impreso.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('titulo', 'CLIMACO POS')</title>

    <style>
        @page { margin: 20mm 15mm 18mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
            margin: 0;
        }

        /* ---------- Cabecera ---------- */
        .cabecera {
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .cabecera td { vertical-align: top; }

        .cabecera__salon { font-size: 14pt; font-weight: bold; }
        .cabecera__datos { font-size: 8.5pt; color: #555; line-height: 1.5; }

        .cabecera__titulo {
            font-size: 13pt;
            font-weight: bold;
            text-align: right;
        }
        .cabecera__periodo {
            font-size: 9pt;
            color: #555;
            text-align: right;
        }

        /* ---------- Tablas ---------- */
        table { width: 100%; border-collapse: collapse; }

        .datos th {
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #555;
            border-bottom: 1px solid #999;
            padding: 5px 4px;
        }

        .datos td {
            padding: 5px 4px;
            border-bottom: 1px solid #e5e5e5;
        }

        .datos tfoot td {
            border-top: 1.5px solid #1a1a1a;
            border-bottom: 0;
            font-weight: bold;
            padding-top: 7px;
        }

        .num { text-align: right; }

        /* Las filas alternas se leen mejor en papel */
        .datos tbody tr:nth-child(even) { background: #f7f7f7; }

        /* ---------- Bloques ---------- */
        h2 {
            font-size: 10.5pt;
            margin: 20px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ccc;
        }

        .resumen td {
            padding: 4px 4px;
            border-bottom: 1px solid #eee;
        }
        .resumen .etiqueta { color: #555; }

        .destacado {
            font-size: 12pt;
            font-weight: bold;
        }

        .aviso {
            border: 1px solid #b45309;
            background: #fffbeb;
            color: #92400e;
            padding: 8px 10px;
            font-size: 9pt;
            margin: 12px 0;
        }

        /* ---------- Pie ---------- */
        .pie {
            position: fixed;
            bottom: -12mm;
            left: 0; right: 0;
            font-size: 7.5pt;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 4px;
        }

        /**
         * Numeracion de paginas.
         *
         * Dompdf las cuenta solo con estos contadores. En un listado de
         * facturas de un trimestre importa: sin numero de pagina, un
         * papel suelto no se sabe de donde salio.
         */
        .pie__pagina:after {
            content: counter(page) " de " counter(pages);
        }
    </style>
</head>
<body>

<table class="cabecera">
<tr>
    <td width="55%">
        <div class="cabecera__salon">{{ tenant('nombre_comercial') }}</div>
        <div class="cabecera__datos">
            @if (tenant('razon_social')){{ tenant('razon_social') }}<br>@endif
            @if (tenant('nif'))NIF: {{ tenant('nif') }}<br>@endif
            @if (tenant('direccion')){{ tenant('direccion') }}<br>@endif
            @if (tenant('cp') || tenant('municipio')){{ trim(tenant('cp') . ' ' . tenant('municipio')) }}@endif
        </div>
    </td>
    <td width="45%">
        <div class="cabecera__titulo">@yield('titulo')</div>
        <div class="cabecera__periodo">@yield('periodo')</div>
    </td>
</tr>
</table>

@yield('contenido')

<div class="pie">
    <table>
    <tr>
        <td>Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} · CLIMACO POS</td>
        <td class="num">Página <span class="pie__pagina"></span></td>
    </tr>
    </table>
</div>

</body>
</html>
