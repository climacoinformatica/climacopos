@extends('pdf.base')

@section('titulo', 'Informe de profesionales')

@section('periodo')
    {{ $desde->format('d/m/Y') }} a {{ $hasta->format('d/m/Y') }}<br>
    {{ $profesional ?: 'Todos los profesionales' }}
@endsection

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

@section('contenido')

@if (empty($profesionales))
    <p>No hay ventas en ese periodo.</p>
@else

<table class="datos">
<thead>
<tr>
    <th width="34%">Profesional</th>
    <th width="12%" class="num">Tickets</th>
    <th width="14%" class="num">Unidades</th>
    <th width="20%" class="num">Total</th>
    <th width="10%" class="num">%</th>
    <th width="20%" class="num">Comisión</th>
</tr>
</thead>
<tbody>
@foreach ($profesionales as $p)
    <tr>
        <td>{{ $p['etiqueta'] }}</td>
        <td class="num">{{ $p['tickets'] }}</td>
        <td class="num">{{ $euros($p['unidades']) }}</td>
        <td class="num">{{ $euros($p['total']) }}</td>
        <td class="num">{{ $euros($p['comision_pct']) }}</td>
        <td class="num">{{ $euros($p['comision']) }}</td>
    </tr>
@endforeach

{{-- Los totales se suman aqui y no en el controlador: son de esta tabla --}}
<tr>
    <td><strong>TOTAL</strong></td>
    <td class="num"><strong>{{ array_sum(array_column($profesionales, 'tickets')) }}</strong></td>
    <td class="num"></td>
    <td class="num"><strong>{{ $euros(array_sum(array_column($profesionales, 'total'))) }}</strong></td>
    <td class="num"></td>
    <td class="num"><strong>{{ $euros(array_sum(array_column($profesionales, 'comision'))) }}</strong></td>
</tr>
</tbody>
</table>

<p style="margin-top:14px; font-size:8.5pt; color:#555">
    La comisión se calcula sobre el importe con impuesto incluido, con el
    porcentaje que tiene cada profesional en su ficha.
</p>

@endif

@endsection
