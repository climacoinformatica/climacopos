@extends('panel.app')

@section('titulo', 'Inicio')

@php
    $permisos   = \App\Support\Permisos::class;
    $pendientes = \App\Models\Reserva::pendientes()->count();
    $citasHoy   = \App\Models\Reserva::delDia(now())->ocupan()->count();
    $ventasHoy  = \App\Models\Ticket::delDia(now())->cobrados()->sum('total');
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Hola, {{ $usuarioSalon->alias }}</h1>
        <p>{{ tenant('nombre_comercial') }} · {{ $usuarioSalon->perfil->nombre }}</p>
    </div>
</div>

@if ($pendientes > 0 && $usuarioSalon->tienePermiso($permisos::RESERVAS_CONFIRMAR))
    <p class="aviso aviso--pendiente">
        Hay {{ $pendientes }} reserva(s) online sin confirmar.
        <a href="{{ route('panel.agenda') }}" style="text-decoration:underline">Ver la agenda</a>
    </p>
@endif

<div class="menu">
    <a href="{{ route('panel.agenda') }}">
        <img src="{{ asset('img/iconos/horarios.png') }}" alt="" class="menu__icono">
        <strong>Agenda</strong>
        <span>{{ $citasHoy }} cita(s) hoy</span>
    </a>

    @if ($usuarioSalon->tienePermiso($permisos::TPV_VENDER))
        <a href="{{ route('panel.tpv') }}">
            <img src="{{ asset('img/iconos/tpv.png') }}" alt="" class="menu__icono">
            <strong>Punto de venta</strong>
            <span>
                @if ($usuarioSalon->en_formacion)
                    Modo formación · solo efectivo
                @else
                    {{ number_format($ventasHoy, 2, ',', '.') }} € vendidos hoy
                @endif
            </span>
        </a>
    @endif

    @if ($usuarioSalon->tienePermiso($permisos::CATALOGO_EDITAR))
        <a href="{{ route('panel.catalogo.articulos') }}">
            <img src="{{ asset('img/iconos/catalogo.png') }}" alt="" class="menu__icono">
            <strong>Catálogo</strong>
            <span>Servicios, productos, fotos y precios</span>
        </a>
    @endif

    @if ($usuarioSalon->tienePermiso($permisos::CAJA_CIERRE))
        <a href="{{ route('panel.caja') }}">
            <img src="{{ asset('img/iconos/caja.png') }}" alt="" class="menu__icono">
            <strong>Caja</strong>
            <span>Arqueo y cierre de jornada</span>
        </a>
    @endif

    @if ($usuarioSalon->tieneAlgunPermiso([$permisos::USUARIOS_GESTIONAR, $permisos::AGENDA_EDITAR_OTROS]))
        <a href="{{ route('panel.agenda.horarios') }}">
            <img src="{{ asset('img/iconos/horarios.png') }}" alt="" class="menu__icono">
            <strong>Horarios</strong>
            <span>Jornadas, vacaciones y festivos</span>
        </a>
    @endif

    <a href="{{ route('panel.clientes') }}">
        <img src="{{ asset('img/iconos/clientes.png') }}" alt="" class="menu__icono">
        <strong>Clientes</strong>
        <span>Fichas, historial y fórmulas</span>
    </a>

    @if ($usuarioSalon->tienePermiso($permisos::INFORMES_VER))
        <a href="{{ route('panel.informes') }}">
            <img src="{{ asset('img/iconos/informes.png') }}" alt="" class="menu__icono">
            <strong>Informes</strong>
            <span>Ventas, personas y evolución</span>
        </a>
    @endif

    @if ($usuarioSalon->tienePermiso($permisos::AJUSTES_ACCESO))
        <a href="{{ route('panel.ajustes') }}">
            <img src="{{ asset('img/iconos/ajustes.png') }}" alt="" class="menu__icono">
            <strong>Ajustes</strong>
            <span>Reservas y enlace del portal</span>
        </a>
    @endif
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/menu-iconos.css') }}?v=23">
@endpush

@endsection
