@extends('panel.app')

@section('titulo', 'Parte de trabajo')

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
    $t = $datos['totales'];
@endphp

@section('contenido')

<div class="parte">

    <header class="parte__cabecera">
        <div>
            <h1>Parte de trabajo</h1>
            <p>
                {{ tenant('nombre_comercial') }} ·
                {{ $fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}
            </p>
        </div>

        <button type="button" class="boton boton--secundario" onclick="window.print()">
            Imprimir
        </button>
    </header>

    @if ($datos['filas']->isEmpty())
        <p class="campo__pista">No se ha facturado nada este día.</p>
    @else
        <table class="tabla-parte">
            <thead>
                <tr>
                    <th>Profesional</th>
                    <th class="num">Servicios</th>
                    <th class="num">Facturado</th>
                    @if ($t['comisiones'] > 0)
                        <th class="num">Le corresponde</th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @foreach ($datos['filas'] as $fila)
                <tr>
                    <td>{{ $fila['usuario']->nombre }}</td>
                    <td class="num">{{ $fila['servicios'] }}</td>
                    <td class="num">{{ $euros($fila['facturado']) }}</td>
                    @if ($t['comisiones'] > 0)
                        <td class="num">{{ $euros($fila['comision']) }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="num"><strong>{{ $t['servicios'] }}</strong></td>
                    <td class="num"><strong>{{ $euros($t['facturado']) }}</strong></td>
                    @if ($t['comisiones'] > 0)
                        <td class="num"><strong>{{ $euros($t['comisiones']) }}</strong></td>
                    @endif
                </tr>
            </tfoot>
        </table>

        @if ($t['productos'] > 0)
            <p class="parte__nota">
                Incluye {{ $t['productos'] }} producto(s) vendido(s).
            </p>
        @endif

        @if ($t['sin_asignar'] > 0)
            <p class="parte__aviso">
                {{ $t['sin_asignar'] }} línea(s) por {{ $euros($t['sin_asignar_imp']) }}
                sin profesional asignado: no están repartidas.
            </p>
        @endif
    @endif

    <footer class="parte__pie">
        <p>
            Este parte recoge lo <strong>ejecutado</strong> por cada profesional,
            que no siempre coincide con quien lo cobró.
            No sustituye al cierre de caja.
        </p>
        <p>Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }}</p>
    </footer>

</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/produccion.css') }}?v=25">
@endpush

@endsection
