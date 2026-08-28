<div class="informes-columnas">
    <div class="tarjeta">
        <div class="tarjeta__cabecera">
            <h2>Por familia</h2>
            <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'familias']) }}"
               class="boton boton--secundario boton--pequeno">CSV</a>
        </div>
        @include('panel.informes.partes.lista', ['datos' => $datos['familias'], 'unidad' => 'unidades'])
    </div>

    <div class="tarjeta">
        <h2>Servicios frente a productos</h2>
        <p class="tarjeta__ayuda">
            La venta de producto es el margen que muchos salones dejan sobre la mesa.
        </p>
        @include('panel.informes.partes.lista', ['datos' => $datos['tipos'], 'unidad' => null])
    </div>
</div>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Artículos más vendidos</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'articulos']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>

    @if ($datos['articulos'] === [])
        <p class="campo__pista">No hay ventas en este periodo.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Artículo</th><th class="num">Unidades</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                @foreach ($datos['articulos'] as $a)
                    <tr>
                        <td>{{ $a['etiqueta'] }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($a['unidades'], 2, ',', '.'), '0'), ',') }}</td>
                        <td class="num">{{ number_format($a['total'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
