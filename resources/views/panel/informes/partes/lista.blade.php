@php
    $total = array_sum(array_column($datos, 'total'));
@endphp

@if ($datos === [])
    <p class="campo__pista">Sin datos en este periodo.</p>
@else
    <ul class="lista-informe">
        @foreach ($datos as $fila)
            @php $pct = $total > 0 ? ($fila['total'] / $total) * 100 : 0; @endphp
            <li>
                <div class="lista-informe__texto">
                    <span>
                        @if (isset($fila['color']))
                            <i class="punto-color" style="background:{{ $fila['color'] }}"></i>
                        @endif
                        {{ $fila['etiqueta'] }}
                    </span>
                    <strong>{{ number_format($fila['total'], 2, ',', '.') }} €</strong>
                </div>
                <div class="lista-informe__barra">
                    <span style="width: {{ $pct }}%; background: {{ $fila['color'] ?? 'var(--marca)' }}"></span>
                </div>
                <div class="lista-informe__pie">
                    {{ number_format($pct, 1, ',', '.') }}%
                    @if ($unidad && isset($fila[$unidad]))
                        · {{ rtrim(rtrim(number_format($fila[$unidad], 2, ',', '.'), '0'), ',') }}
                        {{ $unidad === 'veces' ? 'cobros' : 'uds' }}
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
