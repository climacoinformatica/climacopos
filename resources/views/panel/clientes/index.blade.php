@extends('panel.app')

@section('titulo', 'Clientes')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Clientes</h1>
        <p>{{ $clientes->total() }} ficha(s)</p>
    </div>
    <a href="{{ route('panel.clientes.crear') }}" class="boton">Nuevo cliente</a>
</div>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="buscar">Buscar</label>
        <input type="text" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}"
               placeholder="Nombre, teléfono o email" autofocus>
    </div>

    <div class="campo">
        <label for="filtro">Mostrar</label>
        <select id="filtro" name="filtro">
            <option value="">Todos</option>
            @foreach ([
                'con_saldo'  => 'Con saldo',
                'con_avisos' => 'Con avisos',
                'inactivos'  => 'Sin venir 6 meses',
                'bloqueados' => 'Bloqueados',
            ] as $clave => $texto)
                <option value="{{ $clave }}" @selected(($filtros['filtro'] ?? '') === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="boton boton--secundario">Filtrar</button>
</form>

@if ($clientes->isEmpty())
    <div class="vacio">
        <h3>No hay clientes con esos criterios</h3>
        <p>Las fichas también se crean desde el TPV al cobrar.</p>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Cliente</th><th>Teléfono</th><th>Profesional</th>
                        <th>Última visita</th><th class="num">Visitas</th>
                        <th class="num">Saldo</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($clientes as $cliente)
                    <tr @class(['fila-anulada' => $cliente->bloqueado])>
                        <td>
                            <strong>{{ $cliente->nombreCompleto() }}</strong>
                            @if ($cliente->avisos_ficha)
                                <div class="aviso-ficha-mini">{{ $cliente->avisos_ficha }}</div>
                            @endif
                        </td>
                        <td>{{ $cliente->telefono ?: '—' }}</td>
                        <td>{{ $cliente->profesionalHabitual?->nombre ?? '—' }}</td>
                        <td>
                            {{ $cliente->ultima_visita?->format('d/m/Y') ?? '—' }}
                            @if ($cliente->ultima_visita && $cliente->ultima_visita->lt(now()->subMonths(6)))
                                <div style="color:var(--aviso);font-size:.7rem">
                                    hace {{ (int) $cliente->ultima_visita->diffInMonths(now()) }} meses
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ $cliente->citas_totales ?? 0 }}</td>
                        <td class="num">
                            @if ($cliente->saldo_monedero > 0)
                                <span class="pastilla pastilla--saldo">
                                    {{ number_format($cliente->saldo_monedero, 2, ',', '.') }} €
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('panel.clientes.ver', $cliente) }}"
                               class="boton boton--secundario boton--pequeno">Ficha</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $clientes->links() }}
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}?v=16">
@endpush

@endsection
