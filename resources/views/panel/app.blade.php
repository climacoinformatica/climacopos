<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ tenant('nombre_comercial') }}</title>

    <link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/agenda.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/avisos.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/planes.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/bonos.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/clientes-tpv.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/clientes.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/fichajes.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/ausencias.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/teclado.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/logotipo.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/produccion.css') }}?v=25">
    <link rel="stylesheet" href="{{ asset('css/tpv.css') }}?v=25">
</head>
<body data-teclado-tactil="{{ optional(\App\Support\SesionSalon::terminal())->ajuste('teclado_tactil', 'auto') ?: 'auto' }}">

@php
    $permisos = \App\Support\Permisos::class;
    $puedeConfirmar = $usuarioSalon->tienePermiso($permisos::RESERVAS_CONFIRMAR);
@endphp

{{-- ---------- Aviso destellante ---------- --}}
@if ($puedeConfirmar)
    <button type="button" id="barraAvisos" class="barra-avisos" hidden
            data-url-contador="{{ route('panel.avisos.contador') }}"
            data-url-lista="{{ route('panel.avisos.lista') }}"
            data-url-resolver="{{ route('panel.avisos.resolver', ['reserva' => '__ID__']) }}"
            data-url-leido="{{ route('panel.avisos.leido', ['aviso' => '__ID__']) }}">
        <span class="barra-avisos__campana">🔔</span>
        <span>Reservas nuevas sin atender</span>
        <span class="barra-avisos__contador" id="contadorAvisos">0</span>
    </button>

    <aside class="panel-avisos" id="panelAvisos" hidden>
        <div class="panel-avisos__cabecera">
            <h2>Avisos</h2>
            <button type="button" class="panel-avisos__cerrar" id="cerrarAvisos">&times;</button>
        </div>
        <ul class="avisos-lista" id="listaAvisos"></ul>
    </aside>
@endif

{{-- Estado de la suscripción --}}
@if (($soloLectura ?? false))
    <div class="banda-solo-lectura">
        CUENTA EN SOLO LECTURA · no se puede vender ni reservar ·
        <a href="{{ route('panel.suscripcion') }}">regularizar el pago</a>
    </div>
@elseif (($estadoSuscripcion ?? null) === 'MOROSA')
    <div class="banda-morosa">
        No hemos podido cobrar tu cuota ·
        <a href="{{ route('panel.suscripcion') }}">revisar forma de pago</a>
    </div>
@elseif (($estadoSuscripcion ?? null) === 'SUSPENDIDA')
    <div class="banda-morosa">
        Tu cuenta pasará a solo lectura esta noche ·
        <a href="{{ route('panel.suscripcion') }}">regularizar ahora</a>
    </div>
@endif

@if ($usuarioSalon->en_formacion)
    <div class="banda-formacion">MODO FORMACIÓN · solo cobros en efectivo</div>
@endif

<header class="cabecera">
    <a href="{{ route('panel.inicio') }}" class="cabecera__marca">
        <img src="{{ logo_salon() }}" alt="{{ tenant('nombre_comercial') }}" class="marca-logo">
    </a>

    <nav class="cabecera__nav">
        <a href="{{ route('panel.inicio') }}" @class(['activo' => request()->routeIs('panel.inicio')])>Inicio</a>

        <a href="{{ route('panel.agenda') }}" @class(['activo' => request()->routeIs('panel.agenda') || request()->routeIs('panel.agenda.cita*')])>Agenda</a>

        @if ($usuarioSalon->tienePermiso($permisos::TPV_VENDER))
            <a href="{{ route('panel.tpv') }}" @class(['activo' => request()->routeIs('panel.tpv*')])>TPV</a>
        @endif

        @if ($usuarioSalon->tienePermiso($permisos::CATALOGO_EDITAR))
            <a href="{{ route('panel.catalogo.articulos') }}" @class(['activo' => request()->routeIs('panel.catalogo.*')])>Catálogo</a>
        @endif

        <a href="{{ route('panel.clientes') }}" @class(['activo' => request()->routeIs('panel.clientes*')])>Clientes</a>

        <a href="{{ route('panel.fichajes') }}" @class(['activo' => request()->routeIs('panel.fichajes*')])>Fichar</a>

        <a href="{{ route('panel.ausencias') }}" @class(['activo' => request()->routeIs('panel.ausencias*')])>Ausencias</a>

        @if ($usuarioSalon->tieneAlgunPermiso([$permisos::USUARIOS_GESTIONAR, $permisos::AGENDA_EDITAR_OTROS]))
            <a href="{{ route('panel.festivos') }}" @class(['activo' => request()->routeIs('panel.festivos*')])>Festivos</a>
        @endif

        <a href="{{ route('panel.bonos.vendidos') }}" @class(['activo' => request()->routeIs('panel.bonos.*')])>Bonos</a>

        @if ($usuarioSalon->tienePermiso($permisos::CAJA_CIERRE))
            <a href="{{ route('panel.caja') }}" @class(['activo' => request()->routeIs('panel.caja*')])>Caja</a>
        @endif

        @if ($usuarioSalon->tieneAlgunPermiso([$permisos::INFORMES_VER, $permisos::INFORMES_VER_PROPIOS]))
            <a href="{{ route('panel.informes') }}" @class(['activo' => request()->routeIs('panel.informes*')])>Informes</a>
        @endif

        {{--
            Producción va SIN permiso a propósito: cada profesional puede
            ver lo suyo, que es su trabajo y su dinero. El controlador se
            encarga de que solo quien gestiona personal vea el de todos,
            incluso si alguien manipula la dirección.
        --}}
        <a href="{{ route('panel.produccion') }}" @class(['activo' => request()->routeIs('panel.produccion*')])>Producción</a>

        @if ($usuarioSalon->tienePermiso($permisos::EMPRESA_FACTURACION))
            <a href="{{ route('panel.suscripcion') }}" @class(['activo' => request()->routeIs('panel.suscripcion*')])>Suscripción</a>
        @endif

        @if ($usuarioSalon->tieneAlgunPermiso([$permisos::USUARIOS_GESTIONAR, $permisos::AGENDA_EDITAR_OTROS]))
            <a href="{{ route('panel.agenda.horarios') }}" @class(['activo' => request()->routeIs('panel.agenda.horarios*')])>Horarios</a>
        @endif

        <a href="{{ route('portal.inicio') }}" target="_blank" class="cabecera__portal" title="Ver el portal de reservas">
            Portal ↗
        </a>
    </nav>

    <div class="cabecera__usuario">
        <span class="cabecera__avatar" style="--color: {{ $usuarioSalon->color_agenda }}">
            {{ $usuarioSalon->iniciales() }}
        </span>
        <span class="cabecera__nombre">{{ $usuarioSalon->alias }}</span>

        <form method="POST" action="{{ route('panel.salir') }}">
            @csrf
            <button type="submit" class="cabecera__salir" title="Cambiar de usuario">Salir</button>
        </form>
    </div>
</header>

<main class="contenido">
    @if (session('exito'))
        <p class="aviso aviso--ok">{{ session('exito') }}</p>
    @endif

    @if (session('error'))
        <p class="aviso aviso--error">{{ session('error') }}</p>
    @endif

    @if ($errors->any())
        <div class="aviso aviso--error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('contenido')
</main>

@if ($puedeConfirmar)
    <script src="{{ asset('js/avisos.js') }}?v=25"></script>
@endif

{{-- Teclado en pantalla: se engancha solo a los campos que lo piden --}}
<script src="{{ asset('js/teclado.js') }}?v=25"></script>

@stack('scripts')
</body>
</html>
