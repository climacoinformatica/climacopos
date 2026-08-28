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
        .selector { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem 4rem; }
        .selector__cabecera { text-align: center; margin-bottom: 2.5rem; }
        .selector__logo { max-height: 72px; margin-bottom: 1rem; }
        .selector__cabecera h1 { font-size: 1.6rem; font-weight: 600; }
        .selector__terminal { color: var(--suave); font-size: .85rem; margin-top: .35rem; }

        .rejilla {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
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
            .rejilla { grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: .75rem; }
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
