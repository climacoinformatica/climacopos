@php $r = $datos['resumen']; @endphp

<div class="cifras">
    <div class="cifra">
        <span>Clientes nuevos</span>
        <strong>{{ $r['nuevos'] }}</strong>
    </div>
    <div class="cifra">
        <span>Recurrentes</span>
        <strong>{{ $r['recurrentes'] }}</strong>
    </div>
    <div class="cifra">
        <span>Tickets identificados</span>
        <strong>{{ $r['pct_identificados'] }}%</strong>
    </div>
    <div class="cifra">
        <span>Ventas sin ficha</span>
        <strong>{{ $r['tickets_sin_ficha'] }}</strong>
    </div>
</div>

<p class="aviso aviso--info">
    Un ticket sin cliente asignado es una venta que no se puede fidelizar después.
    Cuanto más suba el porcentaje de identificados, más valen los informes de clientes.
</p>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Mejores clientes</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'mejores']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>

    @if ($datos['mejores'] === [])
        <p class="campo__pista">Sin datos. Asigna clientes a los tickets desde el TPV.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Cliente</th><th>Teléfono</th><th class="num">Visitas</th>
                        <th class="num">Gasto medio</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['mejores'] as $c)
                    <tr>
                        <td>{{ $c['etiqueta'] }}</td>
                        <td>{{ $c['telefono'] ?: '—' }}</td>
                        <td class="num">{{ $c['visitas'] }}</td>
                        <td class="num">{{ number_format($c['medio'], 2, ',', '.') }} €</td>
                        <td class="num">{{ number_format($c['total'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Clientes que no vuelven</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'inactivos']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>

    <p class="tarjeta__ayuda">
        Más de seis meses sin venir. Es la lista para una campaña de recuperación:
        recuperar a una clienta antigua cuesta mucho menos que conseguir una nueva.
    </p>

    @if ($datos['inactivos'] === [])
        <p class="campo__pista">No hay clientes inactivos. Buena señal.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Cliente</th><th>Teléfono</th><th>Última visita</th>
                        <th class="num">Meses</th><th class="num">Visitas</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['inactivos'] as $c)
                    <tr>
                        <td>{{ $c['etiqueta'] }}</td>
                        <td>{{ $c['telefono'] ?: '—' }}</td>
                        <td>{{ $c['ultima'] }}</td>
                        <td class="num">{{ $c['meses'] }}</td>
                        <td class="num">{{ $c['visitas'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
