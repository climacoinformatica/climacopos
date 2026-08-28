<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Administración') · CLIMACO POS</title>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=11">
    <link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=11">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=11">
</head>
<body>

@isset($superadmin)
<header class="cabecera cabecera--admin">
    <a href="{{ route('admin.inicio') }}" class="cabecera__marca">
        CLIMACO POS <span class="insignia-admin">administración</span>
    </a>

    <nav class="cabecera__nav">
        <a href="{{ route('admin.inicio') }}" @class(['activo' => request()->routeIs('admin.inicio')])>Empresas</a>
        <a href="{{ route('admin.ajustes.pagos') }}" @class(['activo' => request()->routeIs('admin.ajustes.*')])>Pagos</a>
        <a href="{{ route('admin.correo') }}" @class(['activo' => request()->routeIs('admin.correo*')])>Correo</a>
    </nav>

    <div class="cabecera__usuario">
        <span class="cabecera__nombre">{{ $superadmin->nombre }}</span>
        <form method="POST" action="{{ route('admin.salir') }}">
            @csrf
            <button type="submit" class="cabecera__salir">Salir</button>
        </form>
    </div>
</header>
@endisset

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

@stack('scripts')
</body>
</html>
