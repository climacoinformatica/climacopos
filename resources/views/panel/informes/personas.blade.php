<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Ventas y comisiones por profesional</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'profesionales']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>

    <p class="tarjeta__ayuda">
        Cada línea del ticket guarda quién realizó el servicio, así que las cifras
        salen de la ejecución real y no de quién cobró.
    </p>

    @if ($datos['profesionales'] === [])
        <p class="campo__pista">No hay ventas en este periodo.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Profesional</th><th class="num">Tickets</th>
                        <th class="num">Unidades</th><th class="num">Ventas</th>
                        <th class="num">Comisión</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($datos['profesionales'] as $p)
                    <tr>
                        <td>
                            <span class="punto-color" style="background:{{ $p['color'] }}"></span>
                            {{ $p['etiqueta'] }}
                        </td>
                        <td class="num">{{ $p['tickets'] }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($p['unidades'], 2, ',', '.'), '0'), ',') }}</td>
                        <td class="num">{{ number_format($p['total'], 2, ',', '.') }} €</td>
                        <td class="num">
                            @if ($p['comision_pct'] > 0)
                                {{ number_format($p['comision'], 2, ',', '.') }} €
                                <small style="color:var(--suave)">({{ rtrim(rtrim(number_format($p['comision_pct'], 2, ',', '.'), '0'), ',') }}%)</small>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Por medio de pago</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'medios']) }}"
           class="boton boton--secundario boton--pequeno">CSV</a>
    </div>
    @include('panel.informes.partes.lista', ['datos' => $datos['medios'], 'unidad' => 'veces'])
</div>
