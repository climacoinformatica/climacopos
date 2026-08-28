<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Invitaciones</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'invitaciones']) }}"
           class="boton boton--secundario boton--pequeno">CSV</a>
    </div>

    <p class="tarjeta__ayuda">
        Servicios servidos a coste cero. Van en informe aparte y no como descuento
        comercial: son dos cosas distintas y conviene poder mirarlas por separado.
    </p>

    @if ($datos['invitaciones'] === [])
        <p class="campo__pista">No hay invitaciones en este periodo.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Documento</th><th>Fecha</th><th>Artículo</th>
                        <th>Motivo</th><th>Usuario</th><th class="num">Valor</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['invitaciones'] as $i)
                    <tr>
                        <td>{{ $i['documento'] }}</td>
                        <td>{{ $i['fecha'] }}</td>
                        <td>{{ $i['etiqueta'] }}</td>
                        <td>{{ $i['motivo'] }}</td>
                        <td>{{ $i['usuario'] }}</td>
                        <td class="num">{{ number_format($i['total'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="num">Total invitado</th>
                        <th class="num">
                            {{ number_format(array_sum(array_column($datos['invitaciones'], 'total')), 2, ',', '.') }} €
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

<div class="tarjeta">
    <h2>Tickets anulados</h2>

    @if ($datos['anulaciones'] === [])
        <p class="campo__pista">No hay anulaciones en este periodo.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Documento</th><th>Fecha</th><th>Usuario</th>
                        <th>Motivo</th><th class="num">Importe</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['anulaciones'] as $a)
                    <tr>
                        <td>{{ $a['documento'] }}</td>
                        <td>{{ $a['fecha'] }}</td>
                        <td>{{ $a['usuario'] }}</td>
                        <td>{{ $a['motivo'] }}</td>
                        <td class="num">{{ number_format($a['total'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Libro de facturas</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'libro']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>

    <p class="tarjeta__ayuda">
        Todos los documentos emitidos, cobrados y anulados, en orden de numeración.
        Es lo que pide la gestoría y la base del libro registro de VERI*FACTU.
        Los documentos de formación no aparecen: no son facturas.
    </p>

    @if ($datos['libro'] === [])
        <p class="campo__pista">No hay documentos en este periodo.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Documento</th><th>Fecha</th><th>Estado</th>
                        <th class="num">Base</th><th class="num">Impuesto</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['libro'] as $l)
                    <tr @class(['fila-anulada' => $l['estado'] === 'ANULADO'])>
                        <td>{{ $l['documento'] }}</td>
                        <td>{{ $l['fecha'] }}</td>
                        <td>{{ ucfirst(strtolower($l['estado'])) }}</td>
                        <td class="num">{{ number_format($l['base'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($l['impuesto'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($l['total'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
