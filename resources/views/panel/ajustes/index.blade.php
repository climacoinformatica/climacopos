@extends('panel.app')

@section('titulo', 'Ajustes')

@php $permisos = \App\Support\Permisos::class; @endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Ajustes</h1>
        <p>{{ $empresa->nombre_comercial }}</p>
    </div>
</div>

<div class="rejilla-ajustes">

    {{-- ---------- Equipo ---------- --}}
    @if ($usuarioSalon->tienePermiso($permisos::USUARIOS_GESTIONAR))
        <a href="{{ route('panel.usuarios') }}" class="tarjeta-ajuste">
            <strong>Usuarios</strong>
            <span>Dar de alta al equipo, PIN de acceso y comisiones</span>
        </a>
    @endif

    @if ($usuarioSalon->tieneAlgunPermiso([$permisos::USUARIOS_GESTIONAR, $permisos::AGENDA_EDITAR_OTROS]))
        <a href="{{ route('panel.agenda.horarios') }}" class="tarjeta-ajuste">
            <strong>Horarios</strong>
            <span>Jornadas de cada profesional y excepciones</span>
        </a>

        <a href="{{ route('panel.festivos') }}" class="tarjeta-ajuste">
            <strong>Festivos y cierres</strong>
            <span>Días que el salón no abre</span>
        </a>
    @endif

    {{-- ---------- El salón ---------- --}}
    <a href="{{ route('panel.ajustes.reservas') }}" class="tarjeta-ajuste">
        <strong>Reservas online</strong>
        <span>Antelación, confirmación y enlace del portal</span>
    </a>

    @if ($usuarioSalon->tienePermiso($permisos::CATALOGO_EDITAR))
        <a href="{{ route('panel.bonos.plantillas') }}" class="tarjeta-ajuste">
            <strong>Bonos y packs</strong>
            <span>Lo que el salón pone a la venta</span>
        </a>
    @endif

    {{-- ---------- Cobros ---------- --}}
    @if ($usuarioSalon->tienePermiso($permisos::EMPRESA_FACTURACION))
        <a href="{{ route('panel.ajustes.pagos') }}" class="tarjeta-ajuste">
            <strong>Cobros online</strong>
            <span>Tarjeta, anticipos y devoluciones</span>
        </a>

        <a href="{{ route('panel.suscripcion') }}" class="tarjeta-ajuste">
            <strong>Suscripción</strong>
            <span>Tu plan y tus facturas de CLIMACO POS</span>
        </a>
    @endif

    {{-- ---------- Equipamiento ---------- --}}
    @if ($usuarioSalon->tienePermiso($permisos::AJUSTES_ACCESO))
        <a href="{{ route('panel.ajustes.hardware') }}" class="tarjeta-ajuste">
            <strong>Impresora y terminales</strong>
            <span>Tickets, cajón y teclado en pantalla</span>
        </a>

        <a href="{{ route('panel.ajustes.ticket') }}" class="tarjeta-ajuste">
            <strong>Diseño del ticket</strong>
            <span>Qué sale impreso y en qué orden</span>
        </a>
    @endif

</div>

{{-- ---------- Logotipo ---------- --}}
@if ($usuarioSalon->tienePermiso($permisos::AJUSTES_ACCESO))
    @include('panel.ajustes.logotipo')
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/ajustes-indice.css') }}?v=26">
<link rel="stylesheet" href="{{ asset('css/logotipo.css') }}?v=26">
@endpush

@endsection
