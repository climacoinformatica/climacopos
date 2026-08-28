@extends('portal.base')

@section('titulo', 'Tu cita ' . $reserva->codigo)

@section('contenido')

@php
    $estados = [
        'PENDIENTE'  => ['Pendiente de confirmar', 'aviso'],
        'CONFIRMADA' => ['¡Cita confirmada!', 'ok'],
        'EN_CURSO'   => ['En curso', 'ok'],
        'ATENDIDA'   => ['Cita atendida', 'neutro'],
        'RECHAZADA'  => ['Cita rechazada', 'error'],
        'CANCELADA'  => ['Cita cancelada', 'error'],
        'NO_SHOW'    => ['No acudiste a esta cita', 'error'],
    ];
    [$texto, $color] = $estados[$reserva->estado] ?? ['Cita', 'neutro'];
@endphp

<div class="tarjeta-cita tarjeta-cita--{{ $color }}">
    <h1 class="portal-titulo">{{ $texto }}</h1>

    @if ($reserva->estado === 'PENDIENTE')
        <p class="tarjeta-cita__nota">
            Hemos recibido tu solicitud. El salón la revisará y te avisaremos
            @if ($reserva->cliente_email) por email @else por teléfono @endif
            en cuanto esté confirmada.
        </p>
    @elseif ($reserva->estado === 'RECHAZADA' && $reserva->motivo_rechazo)
        <p class="tarjeta-cita__nota">{{ $reserva->motivo_rechazo }}</p>
    @endif

    <p class="codigo-cita">{{ $reserva->codigo }}</p>
    <small>Guarda este código para cualquier gestión</small>

    <dl>
        <div>
            <dt>Cuándo</dt>
            <dd>
                {{ $reserva->fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}<br>
                <strong>{{ substr($reserva->hora_ini, 0, 5) }}</strong>
            </dd>
        </div>
        <div>
            <dt>Qué</dt>
            <dd>{{ $reserva->resumenServicios() }}</dd>
        </div>
        <div>
            <dt>Con</dt>
            <dd>{{ $reserva->lineas->first()?->usuario?->nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt>Importe</dt>
            <dd>{{ number_format($reserva->importe_total, 2, ',', '.') }} €</dd>
        </div>
    </dl>

    @if ($reserva->estaAbierta())
        <div class="acciones-cita">
            @if ($empresa->telefono)
                <a href="tel:{{ $empresa->telefono }}" class="boton-portal boton-portal--suave">
                    Llamar al salón
                </a>
            @endif

            @if ($puedeCancelar)
                <form method="POST" action="{{ route('portal.cancelar', $reserva->codigo) }}"
                      onsubmit="return confirm('¿Seguro que quieres cancelar tu cita?')">
                    @csrf
                    <button type="submit" class="boton-portal boton-portal--suave">Cancelar cita</button>
                </form>
            @else
                <p class="letra-pequena">
                    Para cambiar o cancelar esta cita, llámanos.
                </p>
            @endif
        </div>
    @endif
</div>

<p style="text-align:center;margin-top:1.5rem">
    <a href="{{ route('portal.inicio') }}" class="enlace-suave">Reservar otra cita</a>
</p>

@endsection
