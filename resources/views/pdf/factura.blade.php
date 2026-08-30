@extends('pdf.base')

@section('titulo', 'Factura ' . $ticket->referencia())

@section('periodo')
    {{ $ticket->referencia() }}<br>
    {{ $ticket->fecha->format('d/m/Y H:i') }}
@endsection

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

@section('contenido')

{{-- ---------- Datos del cliente ---------- --}}
<table class="datos" style="margin-bottom:16px">
<tbody>
<tr>
    <td width="18%"><strong>Cliente</strong></td>
    {{--
        La ficha de cliente no guarda NIF ni direccion.

        Para una factura completa con datos fiscales harian falta esas
        columnas. Mientras tanto se identifica con lo que hay, que es
        suficiente para una factura simplificada.
    --}}
    <td>
        @if ($ticket->cliente)
            {{ $ticket->cliente->nombreCompleto() }}
            @if ($ticket->cliente->telefono)
                · {{ $ticket->cliente->telefono }}
            @endif
        @else
            Cliente de contado
        @endif
    </td>
</tr>
</tbody>
</table>

{{-- ---------- Lineas ---------- --}}
<table class="datos">
<thead>
<tr>
    <th width="44%">Concepto</th>
    <th width="10%" class="num">Cant.</th>
    <th width="14%" class="num">Precio</th>
    <th width="10%" class="num">Imp. %</th>
    <th width="22%" class="num">Importe</th>
</tr>
</thead>
<tbody>
@foreach ($ticket->lineas as $linea)
    <tr>
        <td>
            {{ $linea->descripcion }}
            @if ($linea->es_invitacion)
                <br><small>Invitación{{ $linea->motivo_invitacion ? ': ' . $linea->motivo_invitacion : '' }}</small>
            @endif
        </td>
        <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 3, ',', '.'), '0'), ',') }}</td>
        <td class="num">{{ $euros($linea->precio) }}</td>
        <td class="num">{{ $euros($linea->impuesto_pct) }}</td>
        <td class="num">{{ $euros($linea->importe) }}</td>
    </tr>
@endforeach
</tbody>
</table>

{{--
    Desglose por tipo de impuesto.

    Aunque el ticket lleve un solo tipo, se saca igual: es lo que pide la
    gestoria y lo que exige una factura para poder deducirse.
--}}
<table class="datos" style="margin-top:16px">
<thead>
<tr>
    <th width="34%">Tipo</th>
    <th width="33%" class="num">Base</th>
    <th width="33%" class="num">Cuota</th>
</tr>
</thead>
<tbody>
@foreach ($porImpuesto as $tipo => $importes)
    <tr>
        <td>{{ $euros($tipo) }} %</td>
        <td class="num">{{ $euros($importes['base']) }}</td>
        <td class="num">{{ $euros($importes['cuota']) }}</td>
    </tr>
@endforeach
<tr>
    <td><strong>TOTAL</strong></td>
    <td class="num"><strong>{{ $euros($ticket->base) }}</strong></td>
    <td class="num"><strong>{{ $euros($ticket->impuesto) }}</strong></td>
</tr>
</tbody>
</table>

<table class="datos" style="margin-top:14px">
<tbody>
<tr>
    <td width="70%" style="text-align:right"><strong>TOTAL FACTURA</strong></td>
    <td width="30%" class="num"><strong>{{ $euros($ticket->total) }} €</strong></td>
</tr>
</tbody>
</table>

@if ($ticket->cobros->isNotEmpty())
    <table class="datos" style="margin-top:14px">
    <thead>
    <tr>
        <th width="70%">Forma de pago</th>
        <th width="30%" class="num">Importe</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($ticket->cobros as $cobro)
        <tr>
            <td>{{ App\Models\TicketCobro::MEDIOS[$cobro->medio] ?? $cobro->medio }}</td>
            <td class="num">{{ $euros($cobro->importe) }}</td>
        </tr>
    @endforeach
    </tbody>
    </table>
@endif

@if ($ticket->estado === 'ANULADO')
    <p style="margin-top:16px"><strong>DOCUMENTO ANULADO</strong></p>
@endif

@endsection
