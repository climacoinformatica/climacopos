@extends('pdf.base')

@section('titulo', 'Cierre de jornada')

@section('periodo')
    {{ $cierre->fecha_ini->format('d/m/Y H:i') }}
    a {{ $cierre->fecha_fin->format('d/m/Y H:i') }}<br>
    Cerrado por {{ $cierre->usuario?->nombre ?? '—' }}
@endsection

@php $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €'; @endphp

@section('contenido')

@if ($cierre->hayDescuadre())
    <div class="aviso">
        <strong>Descuadre de {{ $euros(abs($cierre->descuadre)) }}</strong> ·
        {{ $cierre->descuadre > 0 ? 'sobraba' : 'faltaba' }} dinero en el cajón.
    </div>
@endif

<h2>Totales</h2>

<table class="resumen">
<tr>
    <td class="etiqueta" width="60%">Documentos emitidos</td>
    <td class="num">{{ $cierre->num_tickets }}</td>
</tr>
<tr>
    <td class="etiqueta">Base imponible</td>
    <td class="num">{{ $euros($cierre->total_base) }}</td>
</tr>
<tr>
    <td class="etiqueta">Impuesto</td>
    <td class="num">{{ $euros($cierre->total_impuesto) }}</td>
</tr>
<tr>
    <td class="etiqueta destacado">Total ventas</td>
    <td class="num destacado">{{ $euros($cierre->total_ventas) }}</td>
</tr>
</table>

<h2>Por medio de pago</h2>

<table class="datos">
<thead>
<tr><th>Medio</th><th class="num">Importe</th></tr>
</thead>
<tbody>
@foreach ($cierre->totales_por_medio ?? [] as $medio => $importe)
    <tr>
        <td>{{ ucfirst(strtolower(str_replace('_', ' ', $medio))) }}</td>
        <td class="num">{{ $euros($importe) }}</td>
    </tr>
@endforeach
</tbody>
<tfoot>
<tr>
    <td>Total</td>
    <td class="num">{{ $euros(array_sum($cierre->totales_por_medio ?? [])) }}</td>
</tr>
</tfoot>
</table>

<h2>Arqueo de efectivo</h2>

<table class="resumen">
<tr>
    <td class="etiqueta" width="60%">Fondo inicial</td>
    <td class="num">{{ $euros($cierre->efectivo_inicial) }}</td>
</tr>
<tr>
    <td class="etiqueta">Debería haber</td>
    <td class="num">{{ $euros($cierre->efectivo_teorico) }}</td>
</tr>
<tr>
    <td class="etiqueta">Contado</td>
    <td class="num">{{ $euros($cierre->efectivo_contado) }}</td>
</tr>
<tr>
    <td class="etiqueta destacado">Descuadre</td>
    <td class="num destacado">{{ $euros($cierre->descuadre) }}</td>
</tr>
</table>

@if (! empty($cierre->totales_por_familia))
    <h2>Por familia</h2>

    <table class="datos">
    <thead>
    <tr><th>Familia</th><th class="num">Importe</th></tr>
    </thead>
    <tbody>
    @foreach ($cierre->totales_por_familia as $familia => $importe)
        <tr>
            <td>{{ $familia }}</td>
            <td class="num">{{ $euros($importe) }}</td>
        </tr>
    @endforeach
    </tbody>
    </table>
@endif

@if ($cierre->observaciones)
    <h2>Observaciones</h2>
    <p style="font-size:9pt;line-height:1.6">{{ $cierre->observaciones }}</p>
@endif

{{--
    El reparto por profesional NO sale aquí a propósito.

    El cierre lo maneja quien cuadra el efectivo; lo que factura cada
    persona es información laboral y va en el parte de trabajo, aparte.
--}}

@endsection
