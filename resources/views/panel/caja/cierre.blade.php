@extends('panel.app')

@section('titulo', 'Cierre del ' . $cierre->fecha_fin->format('d/m/Y'))

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Cierre de jornada</h1>
        <p>
            {{ $cierre->fecha_ini->format('d/m/Y H:i') }} →
            {{ $cierre->fecha_fin->format('d/m/Y H:i') }} ·
            {{ $cierre->usuario?->nombre }}
        </p>
    </div>
    <a href="{{ route('panel.caja') }}" class="boton boton--secundario">Volver</a>
</div>

@if ($cierre->hayDescuadre())
    <p class="aviso aviso--error">
        Descuadre de {{ number_format($cierre->descuadre, 2, ',', '.') }} €
        ({{ $cierre->descuadre > 0 ? 'sobraba' : 'faltaba' }} dinero en el cajón).
    </p>
@endif

<div class="tarjeta">
    <h2>Totales</h2>
    <div class="arqueo">
        <div class="arqueo__dato arqueo__dato--destacado">
            <span>Ventas</span>
            <strong>{{ number_format($cierre->total_ventas, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Base</span>
            <strong>{{ number_format($cierre->total_base, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Impuesto</span>
            <strong>{{ number_format($cierre->total_impuesto, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Tickets</span>
            <strong>{{ $cierre->num_tickets }}</strong>
        </div>
        <div class="arqueo__dato">
            <span>Ticket medio</span>
            <strong>{{ number_format($cierre->ticketMedio(), 2, ',', '.') }} €</strong>
        </div>
    </div>
</div>

<div class="tarjeta">
    <h2>Arqueo de efectivo</h2>
    <div class="arqueo">
        <div class="arqueo__dato">
            <span>Debía haber</span>
            <strong>{{ number_format($cierre->efectivo_teorico, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Contado</span>
            <strong>{{ number_format($cierre->efectivo_contado, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato {{ $cierre->hayDescuadre() ? 'arqueo__dato--alerta' : '' }}">
            <span>Descuadre</span>
            <strong>{{ number_format($cierre->descuadre, 2, ',', '.') }} €</strong>
        </div>
    </div>

    @if ($cierre->observaciones)
        <p class="campo__pista" style="margin-top:1rem">{{ $cierre->observaciones }}</p>
    @endif
</div>

@foreach ([
    'Por medio de pago'  => $cierre->totales_por_medio,
    'Por familia'        => $cierre->totales_por_familia,
    'Por profesional'    => $cierre->totales_por_profesional,
] as $titulo => $datos)
    @if (! empty($datos))
        <div class="tarjeta">
            <h2>{{ $titulo }}</h2>
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <tbody>
                    @foreach ($datos as $clave => $importe)
                        <tr>
                            <td>{{ \App\Models\TicketCobro::MEDIOS[$clave] ?? $clave }}</td>
                            <td class="num">{{ number_format($importe, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

<div class="tarjeta">
    <h2>Tickets incluidos</h2>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr><th>Documento</th><th>Hora</th><th>Cobro</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
            @foreach ($tickets as $ticket)
                <tr>
                    <td>{{ $ticket->referencia() }}</td>
                    <td>{{ $ticket->fecha->format('H:i') }}</td>
                    <td>{{ $ticket->medios() }}</td>
                    <td class="num">{{ number_format($ticket->total, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
