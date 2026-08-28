@php
    $bajos = array_filter($datos['articulos'], fn ($a) => $a['bajo']);
    $valor = array_sum(array_column($datos['articulos'], 'valor'));
@endphp

<div class="cifras">
    <div class="cifra">
        <span>Artículos con control</span>
        <strong>{{ count($datos['articulos']) }}</strong>
    </div>
    <div class="cifra {{ count($bajos) > 0 ? 'cifra--alerta' : '' }}">
        <span>Bajo mínimo</span>
        <strong>{{ count($bajos) }}</strong>
    </div>
    <div class="cifra">
        <span>Valor del almacén</span>
        <strong>{{ number_format($valor, 2, ',', '.') }} €</strong>
    </div>
</div>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Existencias</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'stock']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>

    @if ($datos['articulos'] === [])
        <p class="campo__pista">
            Ningún artículo tiene el control de existencias activado.
            Se marca en la ficha de cada producto.
        </p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Artículo</th><th>Familia</th>
                        <th class="num">Stock</th><th class="num">Mínimo</th><th class="num">Valor</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['articulos'] as $a)
                    <tr>
                        <td>{{ $a['etiqueta'] }}</td>
                        <td>{{ $a['familia'] }}</td>
                        <td class="num" @style(['color: var(--error); font-weight: 600' => $a['bajo']])>
                            {{ rtrim(rtrim(number_format($a['stock'], 3, ',', '.'), '0'), ',') }}
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format($a['minimo'], 3, ',', '.'), '0'), ',') }}</td>
                        <td class="num">{{ number_format($a['valor'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
