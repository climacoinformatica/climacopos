@extends('panel.app')

@section('titulo', 'Bono ' . $bono->codigo)

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $bono->plantilla?->nombre }}</h1>
        <p>
            <code>{{ $bono->codigo }}</code> ·
            {{ $bono->cliente?->nombreCompleto() }} ·
            comprado el {{ $bono->comprado_el->format('d/m/Y') }}
        </p>
    </div>
    <a href="{{ route('panel.bonos.vendidos') }}" class="boton boton--secundario">Volver</a>
</div>

@if ($bono->estado !== 'ACTIVO')
    <p class="aviso aviso--error">
        Este bono está {{ strtolower($bono->estado) }}.
        @if ($bono->observaciones)
            {{ $bono->observaciones }}
        @endif
    </p>
@elseif ($bono->diasParaCaducar() !== null && $bono->diasParaCaducar() <= 30)
    <p class="aviso aviso--pendiente">
        Caduca en {{ $bono->diasParaCaducar() }} días,
        el {{ $bono->caduca_el->format('d/m/Y') }}.
    </p>
@endif

<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Disponible</span>
        <strong>{{ $bono->resumen() }}</strong>
    </div>
    <div class="cifra">
        <span>Valor restante</span>
        <strong>{{ number_format($bono->valorRestante(), 2, ',', '.') }} €</strong>
    </div>
    <div class="cifra">
        <span>Pagó</span>
        <strong>{{ number_format($bono->precio_pagado, 2, ',', '.') }} €</strong>
    </div>
    <div class="cifra">
        <span>Caduca</span>
        <strong>{{ $bono->caduca_el?->format('d/m/Y') ?? 'Nunca' }}</strong>
    </div>
</div>

<div class="tarjeta">
    <h2>Movimientos</h2>

    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th><th>Tipo</th><th>Concepto</th>
                    <th>Quién</th><th class="num">Sesiones</th><th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($bono->movimientos as $movimiento)
                <tr>
                    <td>{{ $movimiento->fecha->format('d/m/Y H:i') }}</td>
                    <td>{{ ucfirst(strtolower($movimiento->tipo)) }}</td>
                    <td>
                        {{ $movimiento->concepto }}
                        @if ($movimiento->ticket)
                            <div style="color:var(--suave);font-size:.72rem">
                                {{ $movimiento->ticket->referencia() }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $movimiento->usuario?->nombre ?? '—' }}</td>
                    <td class="num">
                        {{ $movimiento->sesiones != 0
                           ? rtrim(rtrim(number_format($movimiento->sesiones, 2, ',', '.'), '0'), ',')
                           : '—' }}
                    </td>
                    <td class="num">
                        {{ $movimiento->importe != 0
                           ? number_format($movimiento->importe, 2, ',', '.') . ' €'
                           : '—' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($bono->estado === 'ACTIVO')
    <div class="tarjeta" style="max-width:600px">
        <h2>Anular</h2>
        <p class="tarjeta__ayuda">
            El bono deja de poder usarse. No devuelve dinero: para eso hay que
            hacer una devolución del ticket con el que se compró.
        </p>

        <form method="POST" action="{{ route('panel.bonos.anular', $bono) }}"
              onsubmit="return confirm('¿Anular este bono?')">
            @csrf
            <div class="campo">
                <label for="motivo">Motivo *</label>
                <input type="text" id="motivo" name="motivo" required maxlength="255">
            </div>
            <button type="submit" class="boton boton--peligro boton--pequeno">Anular bono</button>
        </form>
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=14">
@endpush

@endsection
