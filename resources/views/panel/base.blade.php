<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ tenant('nombre_comercial') }}</title>
    <style>
        :root {
            --marca: {{ tenant('color_marca') ?: '#111827' }};
            --fondo: #0f172a;
            --panel: #1e293b;
            --borde: #334155;
            --texto: #f1f5f9;
            --suave: #94a3b8;
            --ok: #10b981;
            --error: #ef4444;
            --aviso: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            background: var(--fondo);
            color: var(--texto);
            min-height: 100vh;
            -webkit-tap-highlight-color: transparent;
        }
        /*
         * Alto completo de la ventana y columna flexible: asi el pie
         * de marca se puede empujar al fondo con margin-top: auto,
         * quede mucho o poco contenido encima.
         */
        .selector {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1rem 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .selector__cabecera { text-align: center; margin-bottom: 2.5rem; }
        .selector__logo { max-height: 72px; margin-bottom: 1rem; }
        .selector__cabecera h1 { font-size: 1.6rem; font-weight: 600; }
        .selector__terminal { color: var(--suave); font-size: .85rem; margin-top: .35rem; }

        /* ---------- Pantalla de fichar entrada ---------- */
        .fichaje-entrada {
            max-width: 420px;
            margin: 0 auto;
            padding: 12vh 1rem 2rem;
            text-align: center;
        }
        .fichaje-entrada h1 { font-size: 1.6rem; font-weight: 600; }
        .fichaje-entrada__hora { color: var(--suave); margin-top: .3rem; }
        .fichaje-entrada__pregunta { margin: 2rem 0 1.25rem; font-size: 1.1rem; }
        .fichaje-entrada__botones { display: flex; flex-direction: column; gap: .7rem; }
        .fichaje-entrada__pie {
            margin-top: 1.5rem;
            color: var(--suave);
            font-size: .82rem;
        }

        /* ---------- Estado de fichaje en la tarjeta ---------- */
        .tarjeta__fichaje {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--ok);
        }

        /*
         * El boton de salida va FUERA de la tarjeta: la tarjeta entera
         * es un boton para entrar, y anidar botones no es valido en
         * HTML ni funciona bien al pulsar.
         */
        .salida-fichaje {
            width: 100%;
            margin-top: .4rem;
            padding: .5rem;
            background: transparent;
            border: 1px solid var(--borde);
            border-radius: 10px;
            color: var(--suave);
            font: inherit;
            font-size: .76rem;
            cursor: pointer;
        }
        .salida-fichaje:hover { color: var(--texto); border-color: var(--aviso); }

        /*
         * Pie de marca, al fondo de la ventana.
         *
         * margin-top: auto se come todo el espacio sobrante de la
         * columna, asi que el pie baja del todo aunque solo haya dos
         * usuarios. Los 2 cm de abajo lo separan del borde.
         */
        .selector__pie {
            margin-top: auto;
            padding-top: 3rem;
            padding-bottom: 2cm;
            text-align: center;
        }

        /*
         * El logotipo es trazo blanco sobre transparente, asi que se
         * lee solo sobre el fondo oscuro. Con opacidad para que
         * acompane sin competir con los usuarios, que es lo que hay
         * que pulsar.
         */
        .selector__marca { max-height: 58px; max-width: 260px; opacity: .8; }

        .selector__pie p {
            margin-top: .7rem;
            color: var(--suave);
            font-size: .9rem;
            font-style: italic;
        }

        /*
         * Flexbox y no grid, a proposito.
         *
         * Con grid y repeat(auto-fill, 160px) se creaban columnas
         * VACIAS hasta rellenar el ancho, asi que la rejilla ocupaba
         * todo y justify-content no tenia espacio sobrante que
         * repartir: las tarjetas se quedaban en las primeras columnas,
         * pegadas a la izquierda, por mucho que se pidiera centrar.
         *
         * Con flex-wrap solo existen las tarjetas que hay, y centrarlas
         * funciona con dos usuarios o con ocho.
         */
        .rejilla {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            width: 100%;

            /* Separacion fija de la cabecera: con auto bajaban demasiado */
            margin-top: 6vh;
        }

        .rejilla > li { width: 160px; }

        @media (max-width: 400px) {
            .rejilla > li { width: 132px; }
        }

        .tarjeta {
            width: 100%;
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 14px;
            padding: 1.25rem .75rem 1rem;
            color: inherit;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .6rem;
            transition: transform .12s, border-color .12s;
        }
        .tarjeta:hover, .tarjeta:focus-visible { transform: translateY(-2px); border-color: var(--marca); }
        .tarjeta:active { transform: scale(.97); }
        .tarjeta--formacion { border-color: var(--aviso); }

        .tarjeta__avatar {
            width: 68px; height: 68px; border-radius: 50%;
            background: var(--color, #6366f1);
            display: grid; place-items: center;
            font-size: 1.4rem; font-weight: 600; letter-spacing: .5px;
            overflow: hidden;
        }
        .tarjeta__avatar img { width: 100%; height: 100%; object-fit: cover; }
        .tarjeta__nombre { font-weight: 600; font-size: .95rem; }
        .tarjeta__perfil { color: var(--suave); font-size: .75rem; }
        .tarjeta__etiqueta {
            background: var(--aviso); color: #422006;
            font-size: .65rem; font-weight: 700; letter-spacing: .5px;
            padding: .15rem .5rem; border-radius: 999px;
        }

        .modal {
            position: fixed; inset: 0;
            background: rgba(2, 6, 23, .85);
            display: grid; place-items: center;
            padding: 1rem; z-index: 50;
        }
        .modal[hidden] { display: none; }
        .modal__caja {
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 18px;
            padding: 1.75rem;
            width: 100%; max-width: 340px;
            position: relative;
            text-align: center;
        }
        .modal__cerrar {
            position: absolute; top: .5rem; right: .75rem;
            background: none; border: 0; color: var(--suave);
            font-size: 1.6rem; cursor: pointer; line-height: 1;
        }
        .modal__caja h2 { font-size: 1.15rem; margin-bottom: .25rem; }
        .modal__ayuda { color: var(--suave); font-size: .85rem; margin-bottom: 1.25rem; }

        .puntos { display: flex; justify-content: center; gap: .5rem; margin-bottom: 1.25rem; }
        .punto {
            width: 12px; height: 12px; border-radius: 50%;
            border: 1.5px solid var(--borde);
        }
        .punto--lleno { background: var(--texto); border-color: var(--texto); }

        .teclado { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; }
        .tecla {
            aspect-ratio: 1.5;
            background: #0f172a;
            border: 1px solid var(--borde);
            border-radius: 12px;
            color: var(--texto);
            font-size: 1.35rem;
            cursor: pointer;
        }
        .tecla:active { background: var(--borde); }
        .tecla--ok { background: var(--ok); border-color: var(--ok); color: #04231a; font-weight: 700; }
        .tecla--aux { color: var(--suave); }

        .aviso {
            padding: .7rem .9rem; border-radius: 10px;
            font-size: .85rem; margin: 1rem 0; line-height: 1.45;
        }
        .aviso--ok    { background: rgba(16,185,129,.12);  color: #6ee7b7; }
        .aviso--error { background: rgba(239,68,68,.12);   color: #fca5a5; }
        .aviso code { display: inline-block; margin-top: .4rem; font-size: .8rem; }

        .formulario { max-width: 380px; margin: 3rem auto; padding: 0 1rem; }
        .campo { margin-bottom: 1rem; text-align: left; }
        .campo label { display: block; font-size: .8rem; color: var(--suave); margin-bottom: .35rem; }
        .campo input, .campo select {
            width: 100%; padding: .7rem .8rem;
            background: #0f172a; border: 1px solid var(--borde);
            border-radius: 10px; color: var(--texto); font-size: 1rem;
        }
        .boton {
            width: 100%; padding: .8rem;
            background: var(--marca); color: #fff;
            border: 0; border-radius: 10px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
        }
        .boton--secundario { background: transparent; border: 1px solid var(--borde); color: var(--suave); }

        @media (max-width: 480px) {
            .rejilla { gap: .75rem; margin-top: 3vh; }
            .rejilla > li { width: 115px; }
            .tarjeta__avatar { width: 56px; height: 56px; font-size: 1.2rem; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/teclado.css') }}?v=20">
</head>
<body data-teclado-tactil="{{ optional(\App\Support\SesionSalon::terminal())->ajuste('teclado_tactil', 'auto') ?: 'auto' }}">
    @yield('contenido')

{{-- Teclado en pantalla. En estas pantallas es donde mas falta hace:
     el PIN y la contrasena se teclean con el salon lleno. --}}
<script src="{{ asset('js/teclado.js') }}?v=20"></script>
</body>
</html>
