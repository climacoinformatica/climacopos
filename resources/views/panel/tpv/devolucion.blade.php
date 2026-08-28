@extends('panel.app')

@section('titulo', 'Devolución')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Devolver del ticket {{ $ticket->referencia() }}</h1>
        <p>
            {{ $ticket->fecha->format('d/m/Y H:i') }} ·
            {{ number_format($ticket->total, 2, ',', '.') }} € ·
            {{ $ticket->medios() }}
        </p>
    </div>
    <a href="{{ route('panel.tpv.tickets') }}" class="boton boton--secundario">Volver</a>
</div>

<p class="aviso aviso--info">
    Este ticket ya está cerrado, así que no se puede anular: se emite una
    <strong>factura rectificativa</strong> con su propio número que lo corrige.
    Es un documento nuevo, y el original se queda como está.
</p>

@if ($rectificativas->isNotEmpty())
    <div class="tarjeta">
        <h2>Devoluciones anteriores</h2>
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Documento</th><th>Fecha</th><th>Motivo</th><th class="num">Importe</th></tr>
                </thead>
                <tbody>
                @foreach ($rectificativas as $r)
                    <tr>
                        <td>{{ $r->referencia() }}</td>
                        <td>{{ $r->fecha->format('d/m/Y H:i') }}</td>
                        <td>{{ $r->motivo_rectificacion }}</td>
                        <td class="num">{{ number_format($r->total, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('panel.tpv.devolver', $ticket) }}" id="formDevolucion">
    @csrf

    <div class="tarjeta">
        <h2>Qué se devuelve</h2>

        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th class="num">Vendido</th>
                        <th class="num">Ya devuelto</th>
                        <th class="num">Precio</th>
                        <th class="num">Devolver</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($lineas as $id => $fila)
                    <tr @class(['fila-anulada' => $fila['disponible'] <= 0])>
                        <td>{{ $fila['linea']->descripcion }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($fila['vendido'], 3, ',', '.'), '0'), ',') }}</td>
                        <td class="num">
                            @if ($fila['devuelto'] > 0)
                                {{ rtrim(rtrim(number_format($fila['devuelto'], 3, ',', '.'), '0'), ',') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">{{ number_format($fila['linea']->precio, 2, ',', '.') }} €</td>
                        <td class="num">
                            @if ($fila['disponible'] > 0)
                                <input type="number" name="cantidades[{{ $id }}]"
                                       class="campo-devolver"
                                       min="0" max="{{ $fila['disponible'] }}" step="0.001"
                                       value="0"
                                       data-precio="{{ $fila['linea']->importe / max(0.001, $fila['vendido']) }}"
                                       style="width:90px;text-align:right">
                            @else
                                <span style="color:var(--suave)">nada</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex;gap:.5rem;margin-top:1rem">
            <button type="button" class="boton boton--secundario boton--pequeno" id="devolverTodo">
                Devolver todo
            </button>
            <button type="button" class="boton boton--secundario boton--pequeno" id="limpiar">
                Limpiar
            </button>
        </div>

        <p class="total-devolucion">
            A devolver: <strong id="totalDevolucion">0,00 €</strong>
        </p>
    </div>

    <div class="tarjeta">
        <h2>Cómo se devuelve</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="medio">Medio</label>
                <select id="medio" name="medio" required>
                    @foreach ($medios as $medio)
                        <option value="{{ $medio }}">
                            {{ \App\Models\TicketCobro::MEDIOS[$medio] ?? $medio }}
                        </option>
                    @endforeach
                </select>
                <p class="campo__pista">
                    No tiene por qué ser el mismo con el que se cobró.
                    Si se devuelve en efectivo, saldrá del cajón y afectará al arqueo.
                </p>
            </div>

            <div class="campo">
                <label for="motivo">Motivo *</label>
                <input type="text" id="motivo" name="motivo" required maxlength="255"
                       value="{{ old('motivo') }}"
                       placeholder="Producto en mal estado, servicio no prestado...">
                <p class="campo__pista">Queda en el documento y en la auditoría.</p>
            </div>
        </div>

        <button type="submit" class="boton boton--peligro" id="confirmar" disabled
                onclick="return confirm('¿Emitir la factura rectificativa? No se puede deshacer.')">
            Emitir devolución
        </button>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const campos = document.querySelectorAll('.campo-devolver');
    const total  = document.getElementById('totalDevolucion');
    const boton  = document.getElementById('confirmar');

    function recalcular() {
        let suma = 0;

        campos.forEach(function (campo) {
            const cantidad = parseFloat(campo.value) || 0;
            suma += cantidad * parseFloat(campo.dataset.precio);
        });

        total.textContent = suma.toFixed(2).replace('.', ',') + ' €';
        boton.disabled = suma <= 0;
    }

    campos.forEach(function (campo) {
        campo.addEventListener('input', recalcular);
    });

    document.getElementById('devolverTodo').addEventListener('click', function () {
        campos.forEach(campo => campo.value = campo.max);
        recalcular();
    });

    document.getElementById('limpiar').addEventListener('click', function () {
        campos.forEach(campo => campo.value = 0);
        recalcular();
    });

    recalcular();
})();
</script>
<style>
.total-devolucion {
    text-align: right; font-size: .9rem; color: var(--suave);
    margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--borde);
}
.total-devolucion strong {
    font-size: 1.5rem; color: var(--error);
    display: block; margin-top: .2rem;
    font-variant-numeric: tabular-nums;
}
.campo-devolver {
    padding: .35rem .5rem;
    background: var(--panel2); border: 1px solid var(--borde);
    border-radius: 7px; color: var(--texto);
    font-variant-numeric: tabular-nums;
}
</style>
@endpush

@endsection
