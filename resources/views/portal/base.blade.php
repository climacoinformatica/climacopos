<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $empresa->color_marca ?: '#6366f1' }}">
    <meta name="description" content="Reserva tu cita en {{ $empresa->nombre_comercial }}">
    <title>@yield('titulo', 'Reservar cita') · {{ $empresa->nombre_comercial }}</title>
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=1">
</head>
<body>

<header class="portal-cabecera">
    <a href="{{ route('portal.inicio') }}" class="portal-marca">
        @if ($empresa->logo)
            <img src="{{ tenant_asset($empresa->logo) }}" alt="{{ $empresa->nombre_comercial }}">
        @else
            <span>{{ $empresa->nombre_comercial }}</span>
        @endif
    </a>
</header>

@hasSection('pasos')
    <div class="pasos">
        @yield('pasos')
    </div>
@endif

<main class="portal-contenido">
    @if (session('exito'))
        <p class="nota nota--ok">{{ session('exito') }}</p>
    @endif
    @if (session('error'))
        <p class="nota nota--error">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="nota nota--error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('contenido')
</main>

<footer class="portal-pie">
    <p>{{ $empresa->nombre_comercial }}</p>
    @if ($empresa->direccion)
        <p>{{ $empresa->direccion }}{{ $empresa->municipio ? ', ' . $empresa->municipio : '' }}</p>
    @endif
    @if ($empresa->telefono)
        <p><a href="tel:{{ $empresa->telefono }}">{{ $empresa->telefono }}</a></p>
    @endif
</footer>

@stack('scripts')
</body>
</html>
