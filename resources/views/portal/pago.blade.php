@extends('portal.base')

@section('titulo', 'Pago de la reserva')

@section('contenido')

@php
    $importe = $servicio?->importeFianza() ?? 0;
    $esFianza = $servicio?->politica_pago === 'FIANZA';
@endphp

<div class="tarjeta-cita">
    <h1 class="portal-titulo">
        {{ $esFianza ? 'Confirma con una fianza' : 'Paga tu cita' }}
    </h1>

    <p class="tarjeta-cita__nota">
        @if ($esFianza)
            Para reservar este servicio pedimos una fianza de
            <strong>{{ number_format($importe, 2, ',', '.') }} €</strong>.
            Se descuenta del total el día de la cita.
        @else
            Este servicio se paga por adelantado.
        @endif
    </p>

    <dl>
        <div>
            <dt>Servicio</dt>
            <dd>{{ $reserva->resumenServicios() }}</dd>
        </div>
        <div>
            <dt>Cuándo</dt>
            <dd>
                {{ $reserva->fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}<br>
                <strong>{{ substr($reserva->hora_ini, 0, 5) }}</strong>
            </dd>
        </div>
        <div>
            <dt>Precio del servicio</dt>
            <dd>{{ number_format($reserva->importe_total, 2, ',', '.') }} €</dd>
        </div>
        <div>
            <dt>{{ $esFianza ? 'A pagar ahora' : 'Total' }}</dt>
            <dd><strong>{{ number_format($importe, 2, ',', '.') }} €</strong></dd>
        </div>
        @if ($esFianza)
            <div>
                <dt>El día de la cita</dt>
                <dd>{{ number_format(max(0, $reserva->importe_total - $importe), 2, ',', '.') }} €</dd>
            </div>
        @endif
    </dl>

    <form method="POST" action="{{ route('portal.pago.iniciar', $reserva->codigo) }}"
          style="margin-top:1.5rem">
        @csrf
        <button type="submit" class="boton-portal boton-portal--grande">
            Pagar {{ number_format($importe, 2, ',', '.') }} €
        </button>
    </form>

    <p class="letra-pequena">
        Pago seguro con tarjeta. No guardamos los datos de tu tarjeta en ningún momento.
    </p>

    @if ((int) config_empresa('cancelacion_horas_min', 24) > 0)
        <p class="letra-pequena">
            Si cancelas con más de {{ (int) config_empresa('cancelacion_horas_min', 24) }} horas
            de antelación, te devolvemos el importe íntegro.
        </p>
    @endif
</div>

<p style="text-align:center;margin-top:1.5rem">
    <a href="{{ route('portal.reserva', $reserva->codigo) }}" class="enlace-suave">
        Ver mi cita sin pagar ahora
    </a>
</p>

@endsection
