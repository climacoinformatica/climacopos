@php $r = $datos['reservas']; @endphp

<div class="cifras">
    <div class="cifra">
        <span>Reservas</span>
        <strong>{{ $r['total'] }}</strong>
    </div>
    <div class="cifra">
        <span>Desde el portal</span>
        <strong>{{ $r['pct_online'] }}%</strong>
        <em><small>{{ $r['online'] }} reservas</small></em>
    </div>
    <div class="cifra {{ $r['pct_no_show'] > 10 ? 'cifra--alerta' : '' }}">
        <span>Plantones</span>
        <strong>{{ $r['pct_no_show'] }}%</strong>
        <em><small>{{ $r['no_shows'] }} citas</small></em>
    </div>
    <div class="cifra">
        <span>Canceladas</span>
        <strong>{{ $r['pct_cancelada'] }}%</strong>
        <em><small>{{ $r['canceladas'] }} citas</small></em>
    </div>
</div>

@if ($r['pct_no_show'] > 10)
    <p class="aviso aviso--pendiente">
        Más de uno de cada diez clientes no acude. Valora exigir fianza en los
        servicios largos, o pago por adelantado a quien acumule plantones.
        Se configura en Ajustes.
    </p>
@endif

<div class="tarjeta">
    <h2>Ocupación de la agenda</h2>
    <p class="tarjeta__ayuda">
        Minutos vendidos sobre minutos de horario. No cuenta la pausa de los tintes
        como ocupada: si lo hiciera, un salón lleno de color parecería estar al
        completo teniendo huecos vendibles dentro de las esperas.
    </p>

    @if ($datos['ocupacion'] === [])
        <p class="campo__pista">No hay profesionales con horario configurado.</p>
    @else
        <ul class="lista-informe">
            @foreach ($datos['ocupacion'] as $o)
                <li>
                    <div class="lista-informe__texto">
                        <span>
                            <i class="punto-color" style="background:{{ $o['color'] }}"></i>
                            {{ $o['etiqueta'] }}
                        </span>
                        <strong>{{ $o['porcentaje'] }}%</strong>
                    </div>
                    <div class="lista-informe__barra">
                        <span style="width: {{ min(100, $o['porcentaje']) }}%; background: {{ $o['color'] }}"></span>
                    </div>
                    <div class="lista-informe__pie">
                        {{ $o['horas'] }} h vendidas de
                        {{ round($o['disponible'] / 60, 1) }} h disponibles
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<div class="informes-columnas">
    <div class="tarjeta">
        <h2>Por estado</h2>
        @if ($r['por_estado'] === [])
            <p class="campo__pista">Sin reservas en este periodo.</p>
        @else
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <tbody>
                    @foreach ($r['por_estado'] as $estado => $cuantas)
                        <tr>
                            <td>{{ ucfirst(strtolower(str_replace('_', ' ', $estado))) }}</td>
                            <td class="num">{{ $cuantas }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="tarjeta">
        <h2>Por origen</h2>
        @if ($r['por_origen'] === [])
            <p class="campo__pista">Sin reservas en este periodo.</p>
        @else
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <tbody>
                    @foreach ($r['por_origen'] as $origen => $cuantas)
                        <tr>
                            <td>{{ ucfirst(strtolower($origen)) }}</td>
                            <td class="num">{{ $cuantas }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
