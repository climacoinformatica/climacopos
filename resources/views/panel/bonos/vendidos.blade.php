@extends('panel.app')

@section('titulo', 'Bonos vendidos')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Bonos vendidos</h1>
        <p>{{ $bonos->total() }} bono(s)</p>
    </div>
    <a href="{{ route('panel.bonos.plantillas') }}" class="boton boton--secundario">Catálogo</a>
</div>

@if ($proximos->isNotEmpty())
    <p class="aviso aviso--pendiente">
        <strong>{{ $proximos->count() }} bono(s) caducan este mes.</strong>
        Es buena excusa para llamar: avisar de que le quedan sesiones por usar
        suele traer a la clienta de vuelta.
    </p>
@endif

<form method="GET" class="filtros">
    <div class="campo">
        <label for="buscar">Buscar</label>
        <input type="text" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}"
               placeholder="Código, nombre o teléfono">
    </div>

    <div class="campo">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            @foreach (['ACTIVO' => 'Activos', 'AGOTADO' => 'Agotados',
                       'CADUCADO' => 'Caducados', 'ANULADO' => 'Anulados'] as $clave => $texto)
                <option value="{{ $clave }}" @selected(($filtros['estado'] ?? '') === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="boton boton--secundario">Filtrar</button>
</form>

@if ($bonos->isEmpty())
    <div class="vacio">
        <h3>No hay bonos con esos criterios</h3>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th><th>Cliente</th><th>Bono</th>
                        <th>Disponible</th><th>Caduca</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($bonos as $bono)
                    <tr @class(['fila-anulada' => in_array($bono->estado, ['ANULADO', 'CADUCADO'])])>
                        <td><code style="font-size:.78rem">{{ $bono->codigo }}</code></td>
                        <td>{{ $bono->cliente?->nombreCompleto() ?? '—' }}</td>
                        <td>{{ $bono->plantilla?->nombre }}</td>
                        <td>{{ $bono->resumen() }}</td>
                        <td>
                            @if ($bono->caduca_el)
                                {{ $bono->caduca_el->format('d/m/Y') }}
                                @if ($bono->estado === 'ACTIVO' && $bono->diasParaCaducar() !== null
                                     && $bono->diasParaCaducar() <= 30)
                                    <div style="color:var(--aviso);font-size:.72rem">
                                        {{ $bono->diasParaCaducar() }} días
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="etiqueta {{ $bono->estado !== 'ACTIVO' ? 'etiqueta--inactivo' : '' }}">
                                {{ ucfirst(strtolower($bono->estado)) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('panel.bonos.ver', $bono) }}"
                               class="boton boton--secundario boton--pequeno">Ver</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $bonos->links() }}
@endif

@endsection
