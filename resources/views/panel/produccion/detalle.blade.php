@extends('panel.app')

@section('titulo', 'Detalle de ' . $usuario->nombre)

@php $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €'; @endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $usuario->nombre }}</h1>
        <p>
            {{ $desde->format('d/m/Y') }}
            @unless ($desde->isSameDay($hasta)) – {{ $hasta->format('d/m/Y') }} @endunless
            · {{ $lineas->count() }} línea(s)
        </p>
    </div>
    <a href="{{ route('panel.produccion') }}" class="boton boton--secundario">Volver</a>
</div>

@if ($lineas->isEmpty())
    <div class="vacio"><h3>Nada facturado en esas fechas</h3></div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Documento</th><th>Servicio</th>
                        <th>Cliente</th><th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($lineas as $linea)
                    <tr>
                        <td>{{ $linea->ticket->fecha->format('d/m H:i') }}</td>
                        <td>{{ $linea->ticket->referencia() }}</td>
                        <td>
                            {{ $linea->descripcion }}
                            @if ($linea->cantidad > 1)
                                <span style="color:var(--suave)">× {{ $linea->cantidad }}</span>
                            @endif
                        </td>
                        <td>{{ $linea->ticket->cliente?->nombreCompleto() ?? '—' }}</td>
                        <td class="num">{{ $euros($linea->importe) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fila-total">
                        <td colspan="4"><strong>Total</strong></td>
                        <td class="num"><strong>{{ $euros($lineas->sum('importe')) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=25">
@endpush

@endsection
