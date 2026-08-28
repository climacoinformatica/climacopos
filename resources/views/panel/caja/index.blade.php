@extends('panel.app')

@section('titulo', 'Caja')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Caja y cierre de jornada</h1>
        <p>
            Desde el {{ $resumen['desde']->format('d/m/Y H:i') }} ·
            {{ $resumen['num_tickets'] }} ticket(s)
        </p>
    </div>
    <a href="{{ route('panel.tpv') }}" class="boton boton--secundario">Ir al TPV</a>
</div>

@if ($resumen['formacion'] > 0)
    <p class="aviso aviso--pendiente">
        Hay {{ $resumen['formacion'] }} documento(s) de formación fuera de este cierre.
        <a href="{{ route('panel.caja.formacion') }}" style="text-decoration:underline">Consultarlos</a>
    </p>
@endif

{{-- ---------- Arqueo ---------- --}}
<div class="tarjeta">
    <h2>Resumen de la jornada</h2>

    <div class="arqueo">
        <div class="arqueo__dato arqueo__dato--destacado">
            <span>Ventas</span>
            <strong>{{ number_format($resumen['total_ventas'], 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Base imponible</span>
            <strong>{{ number_format($resumen['total_base'], 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>{{ tenant('regimen_fiscal') === 'IVA' ? 'IVA' : 'IGIC' }}</span>
            <strong>{{ number_format($resumen['total_impuesto'], 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Ticket medio</span>
            <strong>{{ number_format($resumen['ticket_medio'], 2, ',', '.') }} €</strong>
        </div>
    </div>
</div>

{{-- ---------- Medios de pago ---------- --}}
<div class="tarjeta">
    <h2>Por medio de pago</h2>

    @if ($resumen['por_medio'] === [])
        <p class="tarjeta__ayuda">Todavía no hay cobros en esta jornada.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <tbody>
                @foreach ($resumen['por_medio'] as $medio => $importe)
                    <tr>
                        <td>{{ \App\Models\TicketCobro::MEDIOS[$medio] ?? $medio }}</td>
                        <td class="num">{{ number_format($importe, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ---------- Efectivo ---------- --}}
<div class="tarjeta">
    <h2>Efectivo en caja</h2>

    <div class="arqueo">
        <div class="arqueo__dato">
            <span>Fondo inicial</span>
            <strong>{{ number_format($resumen['efectivo_inicial'], 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Ventas en efectivo</span>
            <strong>{{ number_format($resumen['por_medio']['EFECTIVO'] ?? 0, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Entradas</span>
            <strong>{{ number_format($resumen['entradas'], 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Salidas</span>
            <strong>−{{ number_format($resumen['salidas'], 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato arqueo__dato--destacado">
            <span>Debe haber</span>
            <strong>{{ number_format($resumen['efectivo_teorico'], 2, ',', '.') }} €</strong>
        </div>
    </div>

    <h3 style="font-size:.85rem;margin:1.25rem 0 .6rem;color:var(--suave)">
        Registrar movimiento
    </h3>

    <form method="POST" action="{{ route('panel.caja.movimiento') }}">
        @csrf
        <div class="rejilla-campos">
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="APERTURA">Fondo de caja</option>
                    <option value="ENTRADA">Entrada</option>
                    <option value="SALIDA">Salida</option>
                </select>
            </div>
            <div class="campo">
                <label for="importe">Importe</label>
                <input type="number" id="importe" name="importe" step="0.01" min="0.01" required>
            </div>
            <div class="campo">
                <label for="motivo">Motivo</label>
                <input type="text" id="motivo" name="motivo" required
                       placeholder="Cambio, pago a proveedor...">
            </div>
        </div>
        <button type="submit" class="boton boton--pequeno">Registrar</button>
    </form>

    @if ($resumen['movimientos']->isNotEmpty())
        <div class="tabla-envoltorio" style="margin-top:1.25rem">
            <table class="tabla">
                <thead>
                    <tr><th>Hora</th><th>Tipo</th><th>Motivo</th><th class="num">Importe</th></tr>
                </thead>
                <tbody>
                @foreach ($resumen['movimientos'] as $movimiento)
                    <tr>
                        <td>{{ $movimiento->fecha->format('H:i') }}</td>
                        <td>{{ ucfirst(strtolower($movimiento->tipo)) }}</td>
                        <td>{{ $movimiento->motivo }}</td>
                        <td class="num">
                            {{ $movimiento->tipo === 'SALIDA' ? '−' : '' }}{{ number_format($movimiento->importe, 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ---------- Cerrar ---------- --}}
<div class="tarjeta">
    <h2>Cerrar jornada</h2>
    <p class="tarjeta__ayuda">
        Cuenta el efectivo del cajón e introduce el importe. El cierre agrupa
        los {{ $resumen['num_tickets'] }} ticket(s) pendientes y ya no se podrán anular.
    </p>

    <form method="POST" action="{{ route('panel.caja.cerrar') }}"
          onsubmit="return confirm('¿Cerrar la jornada? Los tickets incluidos ya no se podrán anular.')">
        @csrf

        <div class="rejilla-campos">
            <div class="campo">
                <label for="efectivo_contado">Efectivo contado</label>
                <input type="number" id="efectivo_contado" name="efectivo_contado"
                       step="0.01" min="0" required
                       value="{{ number_format($resumen['efectivo_teorico'], 2, '.', '') }}">
                <p class="campo__pista" id="pistaDescuadre"></p>
            </div>

            <div class="campo">
                <label for="observaciones">Observaciones</label>
                <input type="text" id="observaciones" name="observaciones"
                       placeholder="Falta cambio, incidencia...">
            </div>
        </div>

        <button type="submit" class="boton">Cerrar jornada</button>
    </form>
</div>

{{-- ---------- Últimos cierres ---------- --}}
@if ($ultimos->isNotEmpty())
    <div class="tarjeta">
        <h2>Últimos cierres</h2>
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Usuario</th>
                        <th class="num">Tickets</th><th class="num">Ventas</th>
                        <th class="num">Descuadre</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($ultimos as $cierre)
                    <tr>
                        <td>{{ $cierre->fecha_fin->format('d/m/Y H:i') }}</td>
                        <td>{{ $cierre->usuario?->nombre ?? '—' }}</td>
                        <td class="num">{{ $cierre->num_tickets }}</td>
                        <td class="num">{{ number_format($cierre->total_ventas, 2, ',', '.') }} €</td>
                        <td class="num" @style(['color: var(--error)' => $cierre->hayDescuadre()])>
                            {{ number_format($cierre->descuadre, 2, ',', '.') }} €
                        </td>
                        <td>
                            <a href="{{ route('panel.caja.cierre', $cierre) }}"
                               class="boton boton--secundario boton--pequeno">Ver</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@push('scripts')
<script>
const teorico = {{ $resumen['efectivo_teorico'] }};
const campo = document.getElementById('efectivo_contado');
const pista = document.getElementById('pistaDescuadre');

function calcular() {
    const contado = parseFloat(campo.value) || 0;
    const dif = contado - teorico;

    if (Math.abs(dif) < 0.01) {
        pista.textContent = 'Cuadra exactamente.';
        pista.style.color = 'var(--ok)';
    } else {
        pista.textContent = (dif > 0 ? 'Sobran ' : 'Faltan ') +
            Math.abs(dif).toFixed(2).replace('.', ',') + ' €';
        pista.style.color = 'var(--error)';
    }
}

campo.addEventListener('input', calcular);
calcular();
</script>
@endpush

@endsection
