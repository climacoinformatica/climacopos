@extends('panel.app')

@section('titulo', 'Producción')

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
    $t = $datos['totales'];
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Producción</h1>
        <p>
            {{ $datos['desde']->format('d/m/Y') }}
            @unless ($datos['desde']->isSameDay($datos['hasta']))
                – {{ $datos['hasta']->format('d/m/Y') }}
            @endunless
        </p>
    </div>

    <div style="display:flex;gap:.5rem">
        <a href="{{ route('panel.produccion.parte') }}" class="boton boton--secundario">
            Parte de hoy
        </a>

        @if ($gestiona)
            <a href="{{ route('panel.produccion.exportar', $filtros) }}" class="boton boton--secundario">
                Exportar
            </a>
        @endif
    </div>
</div>

{{-- ---------- Atajos ---------- --}}
<div class="atajos-fecha">
    @foreach ([
        'hoy' => 'Hoy', 'ayer' => 'Ayer',
        'semana' => 'Esta semana', 'semana_p' => 'Semana pasada',
        'mes' => 'Este mes', 'mes_p' => 'Mes pasado',
    ] as $clave => $texto)
        <a href="{{ route('panel.produccion', array_merge($filtros, ['atajo' => $clave])) }}"
           @class(['atajo', 'atajo--activo' => ($filtros['atajo'] ?? '') === $clave])>
            {{ $texto }}
        </a>
    @endforeach
</div>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] }}">
    </div>

    <div class="campo">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] }}">
    </div>

    @if ($gestiona)
        <div class="campo">
            <label for="usuario_id">Profesional</label>
            <select id="usuario_id" name="usuario_id">
                <option value="">Todos</option>
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" @selected($filtros['usuario_id'] == $u->id)>
                        {{ $u->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <button type="submit" class="boton boton--secundario">Ver</button>
</form>

{{-- ---------- Cifras ---------- --}}
<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Facturado</span>
        <strong>{{ $euros($t['facturado']) }}</strong>
    </div>
    <div class="cifra">
        <span>Servicios</span>
        <strong>{{ $t['servicios'] }}</strong>
    </div>
    <div class="cifra">
        <span>Productos</span>
        <strong>{{ $t['productos'] }}</strong>
    </div>
    @if ($t['comisiones'] > 0)
        <div class="cifra">
            <span>Comisiones</span>
            <strong>{{ $euros($t['comisiones']) }}</strong>
        </div>
    @endif
</div>

@if ($t['sin_asignar'] > 0)
    <p class="aviso aviso--pendiente">
        Hay <strong>{{ $t['sin_asignar'] }} línea(s)</strong> por
        {{ $euros($t['sin_asignar_imp']) }} <strong>sin profesional asignado</strong>,
        así que no cuentan para nadie. Suele pasar cuando se añade un producto
        sin tener sesión abierta.
    </p>
@endif

{{-- ---------- Tabla ---------- --}}
@if ($datos['filas']->isEmpty())
    <div class="vacio">
        <h3>No hay nada facturado en esas fechas</h3>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Profesional</th>
                        <th class="num">Servicios</th>
                        <th class="num">Productos</th>
                        <th class="num">Facturado</th>
                        <th class="num">Ticket medio</th>
                        @if ($t['comisiones'] > 0)
                            <th class="num">Le corresponde</th>
                        @endif
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($datos['filas'] as $fila)
                    <tr>
                        <td>
                            <span class="punto-color" style="background:{{ $fila['usuario']->color_agenda }}"></span>
                            <strong>{{ $fila['usuario']->nombre }}</strong>
                        </td>
                        <td class="num">{{ $fila['servicios'] }}</td>
                        <td class="num">{{ $fila['productos'] ?: '—' }}</td>
                        <td class="num"><strong>{{ $euros($fila['facturado']) }}</strong></td>
                        <td class="num" style="color:var(--suave)">{{ $euros($fila['medio']) }}</td>

                        @if ($t['comisiones'] > 0)
                            <td class="num">
                                {{ $fila['comision'] > 0 ? $euros($fila['comision']) : '—' }}
                            </td>
                        @endif

                        <td>
                            <a href="{{ route('panel.produccion.detalle', array_merge($filtros, ['usuario' => $fila['usuario']->id])) }}"
                               class="boton boton--secundario boton--pequeno">Detalle</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fila-total">
                        <td><strong>Total</strong></td>
                        <td class="num"><strong>{{ $t['servicios'] }}</strong></td>
                        <td class="num"><strong>{{ $t['productos'] ?: '—' }}</strong></td>
                        <td class="num"><strong>{{ $euros($t['facturado']) }}</strong></td>
                        <td></td>
                        @if ($t['comisiones'] > 0)
                            <td class="num"><strong>{{ $euros($t['comisiones']) }}</strong></td>
                        @endif
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/produccion.css') }}?v=25">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=25">
@endpush

@endsection
