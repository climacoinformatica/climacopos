@extends('panel.app')

@section('titulo', 'Informes')

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
    $maximo = fn (array $lista, string $campo = 'total') => max(1, max(array_column($lista, $campo) ?: [1]));
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Informes</h1>
        <p>
            {{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}
            · {{ $generador->dias() }} día(s)
        </p>
    </div>
</div>

@if ($soloPropios)
    <p class="aviso aviso--info">
        Tu perfil solo permite ver tus propias ventas.
    </p>
@endif

{{-- ---------- Selector de periodo ---------- --}}
<form method="GET" class="periodo">
    <input type="hidden" name="informe" value="{{ $informe }}">

    <div class="periodo__rapidos">
        @foreach ([
            'hoy' => 'Hoy', 'ayer' => 'Ayer',
            'semana' => 'Esta semana', 'semana_pasada' => 'Semana pasada',
            'mes' => 'Este mes', 'mes_pasado' => 'Mes pasado',
            'trimestre' => 'Trimestre', 'ano' => 'Año',
        ] as $clave => $texto)
            <a href="{{ route('panel.informes', ['informe' => $informe, 'rango' => $clave]) }}"
               class="periodo__boton {{ $rango === $clave ? 'periodo__boton--activo' : '' }}">
                {{ $texto }}
            </a>
        @endforeach
    </div>

    <div class="periodo__medida">
        <input type="hidden" name="rango" value="medida">
        <input type="date" name="desde" value="{{ $desde->toDateString() }}">
        <input type="date" name="hasta" value="{{ $hasta->toDateString() }}">
        <button type="submit" class="boton boton--secundario boton--pequeno">Ver</button>
    </div>
</form>

{{-- ---------- Pestañas ---------- --}}
<nav class="informes-nav">
    @foreach ($informes as $clave => [$titulo, $descripcion])
        <a href="{{ route('panel.informes', array_filter(['informe' => $clave, 'rango' => $rango,
                    'desde' => $rango === 'medida' ? $desde->toDateString() : null,
                    'hasta' => $rango === 'medida' ? $hasta->toDateString() : null])) }}"
           class="informes-nav__item {{ $informe === $clave ? 'informes-nav__item--activo' : '' }}"
           title="{{ $descripcion }}">
            {{ $titulo }}
        </a>
    @endforeach
</nav>

@include('panel.informes.' . $informe)

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=7">
@endpush

@endsection
