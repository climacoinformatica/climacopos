@extends('pdf.base')

@section('titulo', 'Listado de facturas')

@section('periodo')
    {{ $desde->format('d/m/Y') }} a {{ $hasta->format('d/m/Y') }}<br>
    {{ $tickets->count() }} documento(s)
@endsection

@php $euros = fn ($n) => number_format((float) $n, 2, ',', '.'); @endphp

@section('contenido')

@if ($tickets->isEmpty())
    <p>No se emitió ningún documento en esas fechas.</p>
@else

<table class="datos">
<thead>
<tr>
    <th width="14%">Número</th>
    <th width="13%">Fecha</th>
    <th width="33%">Cliente</th>
    <th width="13%" class="num">Base</th>
    <th width="13%" class="num">Impuesto</th>
    <th width="14%" class="num">Total</th>
</tr>
</thead>
<tbody>
@foreach ($tickets as $ticket)
    <tr>
        <td>{{ $ticket->referencia() }}</td>
        <td>{{ $ticket->fecha->format('d/m/Y') }}</td>
        <td>
            {{ $ticket->cliente?->nombreCompleto() ?? 'Cliente contado' }}
            @if ($ticket->cliente?->nif)
                <br><span style="font-size:7.5pt;color:#666">{{ $ticket->cliente->nif }}</span>
            @endif
        </td>
        <td class="num">{{ $euros($ticket->base) }}</td>
        <td class="num">{{ $euros($ticket->impuesto) }}</td>
        <td class="num">{{ $euros($ticket->total) }}</td>
    </tr>
@endforeach
</tbody>
<tfoot>
<tr>
    <td colspan="3">TOTAL · {{ $tickets->count() }} documento(s)</td>
    <td class="num">{{ $euros($tickets->sum('base')) }}</td>
    <td class="num">{{ $euros($tickets->sum('impuesto')) }}</td>
    <td class="num">{{ $euros($tickets->sum('total')) }}</td>
</tr>
</tfoot>
</table>

{{--
    Desglose por tipo de impuesto.

    Es lo que pide la gestoria para el modelo trimestral: no le vale el
    total, necesita cuanto hay a cada tipo.
--}}
@if (! empty($porImpuesto) && count($porImpuesto) > 1)
    <h2>Desglose por tipo</h2>

    <table class="datos">
    <thead>
    <tr>
        <th>Tipo</th>
        <th class="num">Base</th>
        <th class="num">Cuota</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($porImpuesto as $tipo => $importes)
        <tr>
            <td>{{ rtrim(rtrim(number_format($tipo, 2, ',', ''), '0'), ',') }} %</td>
            <td class="num">{{ $euros($importes['base']) }}</td>
            <td class="num">{{ $euros($importes['cuota']) }}</td>
        </tr>
    @endforeach
    </tbody>
    </table>
@endif

<p style="font-size:8pt;color:#666;margin-top:18px">
    Los documentos de formación no aparecen en este listado: no tienen
    valor fiscal.
</p>

@endif

@endsection
