@php
    $r = $datos['resumen'];
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
@endphp

<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Ventas</span>
        <strong>{{ $euros($r['ventas']) }}</strong>
        @if ($r['variacion'] !== null)
            <em class="{{ $r['variacion'] >= 0 ? 'sube' : 'baja' }}">
                {{ $r['variacion'] >= 0 ? '▲' : '▼' }} {{ abs($r['variacion']) }}%
                <small>frente a {{ $euros($r['ventas_anterior']) }}</small>
            </em>
        @endif
    </div>

    <div class="cifra">
        <span>Tickets</span>
        <strong>{{ $r['num_tickets'] }}</strong>
    </div>

    <div class="cifra">
        <span>Ticket medio</span>
        <strong>{{ $euros($r['ticket_medio']) }}</strong>
    </div>

    <div class="cifra">
        <span>Venta diaria</span>
        <strong>{{ $euros($r['venta_diaria']) }}</strong>
    </div>

    <div class="cifra">
        <span>Base imponible</span>
        <strong>{{ $euros($r['base']) }}</strong>
    </div>

    <div class="cifra">
        <span>{{ tenant('regimen_fiscal') === 'IVA' ? 'IVA' : 'IGIC' }}</span>
        <strong>{{ $euros($r['impuesto']) }}</strong>
    </div>
</div>

<div class="tarjeta">
    <h2>Ventas por día</h2>
    @include('panel.informes.partes.barras', ['datos' => $datos['por_dia'], 'campo' => 'total', 'etiqueta' => 'etiqueta'])
</div>

<div class="informes-columnas">
    <div class="tarjeta">
        <h2>Por familia</h2>
        @include('panel.informes.partes.lista', ['datos' => $datos['familias'], 'unidad' => 'unidades'])
    </div>

    <div class="tarjeta">
        <h2>Por medio de pago</h2>
        @include('panel.informes.partes.lista', ['datos' => $datos['medios'], 'unidad' => 'veces'])
    </div>
</div>
