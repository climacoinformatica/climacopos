@extends('panel.app')

@section('titulo', 'Vales')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Vales</h1>
        <p>{{ number_format($total, 2, ',', '.') }} € pendientes de canjear</p>
    </div>
</div>

<div class="tarjeta" style="max-width:640px">
    <h2>Emitir un vale</h2>
    <p class="tarjeta__ayuda">
        Sirve como tarjeta regalo o como alternativa a devolver dinero.
        Lleva código porque quien lo canjea no tiene por qué ser quien lo compró.
    </p>

    <form method="POST" action="{{ route('panel.bonos.vales.emitir') }}">
        @csrf
        <div class="rejilla-campos">
            <div class="campo">
                <label for="importe">Importe *</label>
                <input type="number" id="importe" name="importe" step="0.01" min="0.01" required>
            </div>

            <div class="campo">
                <label for="meses">Validez (meses)</label>
                <input type="number" id="meses" name="meses" min="1" max="60" value="12">
            </div>

            <div class="campo">
                <label for="concepto">Concepto</label>
                <input type="text" id="concepto" name="concepto" maxlength="200"
                       placeholder="Regalo de cumpleaños">
            </div>
        </div>

        <button type="submit" class="boton boton--pequeno">Emitir vale</button>
    </form>
</div>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="buscar">Buscar por código</label>
        <input type="text" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}"
               style="text-transform:uppercase">
    </div>
    <button type="submit" class="boton boton--secundario">Buscar</button>
</form>

@if ($vales->isEmpty())
    <div class="vacio"><h3>No hay vales</h3></div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th><th>Origen</th><th>Cliente</th>
                        <th>Emitido</th><th>Caduca</th>
                        <th class="num">Inicial</th><th class="num">Restante</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($vales as $vale)
                    <tr @class(['fila-anulada' => ! $vale->estaDisponible()])>
                        <td><code style="font-size:.82rem">{{ $vale->codigo }}</code></td>
                        <td>{{ ucfirst(strtolower($vale->origen)) }}</td>
                        <td>{{ $vale->cliente?->nombreCompleto() ?? 'Al portador' }}</td>
                        <td>{{ $vale->emitido_el->format('d/m/Y') }}</td>
                        <td>{{ $vale->caduca_el?->format('d/m/Y') ?? '—' }}</td>
                        <td class="num">{{ number_format($vale->importe_inicial, 2, ',', '.') }} €</td>
                        <td class="num">{{ number_format($vale->importe_restante, 2, ',', '.') }} €</td>
                        <td>
                            <span class="etiqueta {{ $vale->estado !== 'ACTIVO' ? 'etiqueta--inactivo' : '' }}">
                                {{ ucfirst(strtolower($vale->estado)) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $vales->links() }}
@endif

@endsection
