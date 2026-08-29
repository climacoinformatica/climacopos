@extends('panel.app')

@section('titulo', 'Informe X')

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Cómo va el día</h1>
        <p>
            Desde {{ $datos['desde']->format('d/m/Y H:i') }} ·
            a las {{ $datos['momento']->format('H:i') }}
        </p>
    </div>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <form method="POST" action="{{ route('panel.caja.informe-x.imprimir') }}">
            @csrf
            <button type="submit" class="boton boton--secundario">Imprimir</button>
        </form>

        <a href="{{ route('panel.tpv') }}" class="boton boton--secundario">Volver al TPV</a>
    </div>
</div>

{{--
    Aviso de que esto NO cierra nada.

    Es la confusion mas facil de tener: alguien mira el informe X, ve los
    totales del dia, y da por hecho que ya ha cerrado. Al dia siguiente
    las ventas de ayer siguen sin cerrar.
--}}
<p class="aviso aviso--pendiente">
    Esto es solo una foto de cómo va el día. <strong>No cierra nada</strong>:
    puedes seguir vendiendo con normalidad.
</p>

<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Vendido</span>
        <strong>{{ $euros($datos['total_ventas']) }}</strong>
    </div>
    <div class="cifra">
        <span>Tickets</span>
        <strong>{{ $datos['num_tickets'] }}</strong>
    </div>
    <div class="cifra">
        <span>Ticket medio</span>
        <strong>{{ $euros($datos['ticket_medio']) }}</strong>
    </div>
    <div class="cifra">
        <span>Debería haber en caja</span>
        <strong>{{ $euros($datos['efectivo_teorico']) }}</strong>
    </div>
</div>

<div class="rejilla-informe">

    {{-- ---------- Medios de pago ---------- --}}
    <div class="tarjeta">
        <h2>Cómo han pagado</h2>

        @if (empty($datos['por_medio']))
            <p class="campo__pista">Todavía no se ha cobrado nada.</p>
        @else
            <table class="tabla">
                <tbody>
                @foreach ($datos['por_medio'] as $medio => $importe)
                    <tr>
                        <td>{{ ucfirst(strtolower(str_replace('_', ' ', $medio))) }}</td>
                        <td class="num"><strong>{{ $euros($importe) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ---------- Profesionales ---------- --}}
    <div class="tarjeta">
        <h2>Por profesional</h2>

        @if (empty($datos['por_profesional']))
            <p class="campo__pista">Nada todavía.</p>
        @else
            <table class="tabla">
                <tbody>
                @foreach ($datos['por_profesional'] as $nombre => $importe)
                    <tr>
                        <td>{{ $nombre }}</td>
                        <td class="num"><strong>{{ $euros($importe) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ---------- Caja ---------- --}}
    <div class="tarjeta">
        <h2>Efectivo</h2>

        <table class="tabla">
            <tbody>
            <tr>
                <td>Fondo con el que se empezó</td>
                <td class="num">{{ $euros($datos['efectivo_inicial']) }}</td>
            </tr>
            <tr>
                <td>Cobrado en efectivo</td>
                <td class="num">{{ $euros($datos['por_medio']['EFECTIVO'] ?? 0) }}</td>
            </tr>
            @if ($datos['entradas'] > 0)
                <tr>
                    <td>Entradas</td>
                    <td class="num">{{ $euros($datos['entradas']) }}</td>
                </tr>
            @endif
            @if ($datos['salidas'] > 0)
                <tr>
                    <td>Salidas</td>
                    <td class="num">− {{ $euros($datos['salidas']) }}</td>
                </tr>
            @endif
            <tr class="fila-total">
                <td><strong>Debería haber</strong></td>
                <td class="num"><strong>{{ $euros($datos['efectivo_teorico']) }}</strong></td>
            </tr>
            </tbody>
        </table>
    </div>

    {{-- ---------- Familias ---------- --}}
    @if (! empty($datos['por_familia']))
        <div class="tarjeta">
            <h2>Por familia</h2>

            <table class="tabla">
                <tbody>
                @foreach ($datos['por_familia'] as $familia => $importe)
                    <tr>
                        <td>{{ $familia }}</td>
                        <td class="num">{{ $euros($importe) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

@if ($datos['formacion'] > 0)
    <p class="campo__pista" style="margin-top:1.5rem">
        Hay {{ $datos['formacion'] }} documento(s) de formación que no cuentan
        aquí: no tienen valor fiscal.
    </p>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/produccion.css') }}?v=36">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=36">
@endpush

@endsection
