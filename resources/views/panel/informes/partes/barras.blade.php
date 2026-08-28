@php
    $valores = array_column($datos, $campo);
    $maximo = max(1, max($valores ?: [1]));
@endphp

@if (array_sum($valores) == 0)
    <p class="campo__pista">Sin datos en este periodo.</p>
@else
    <div class="barras">
        @foreach ($datos as $fila)
            @php $alto = ($fila[$campo] / $maximo) * 100; @endphp
            <div class="barra" title="{{ $fila[$etiqueta] }}: {{ number_format($fila[$campo], 2, ',', '.') }} €">
                <span class="barra__valor">
                    {{ $fila[$campo] > 0 ? number_format($fila[$campo], 0, ',', '.') : '' }}
                </span>
                <span class="barra__columna" style="height: {{ max(1, $alto) }}%"></span>
                <span class="barra__etiqueta">{{ $fila[$etiqueta] }}</span>
            </div>
        @endforeach
    </div>
@endif
