@extends('panel.app')

@section('titulo', 'Facturas')

@php
    $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
    $tickets = $datos['tickets'];
    $filtros = ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Facturas emitidas</h1>
        <p>
            {{ $desde->format('d/m/Y') }} a {{ $hasta->format('d/m/Y') }} ·
            {{ $tickets->count() }} documento(s)
        </p>
    </div>

    <div style="display:flex;gap:.5rem">
        <a href="{{ route('panel.documentos.facturas.pdf', $filtros) }}"
           class="boton boton--secundario">Descargar PDF</a>

        <button type="button" class="boton" onclick="abrirEnvio()">Enviar por correo</button>
    </div>
</div>

{{-- ---------- Atajos ---------- --}}
<div class="atajos-fecha">
    @foreach ([
        'mes' => 'Este mes', 'mes_p' => 'Mes pasado',
        'trimestre' => 'Este trimestre', 'trimestre_p' => 'Trimestre pasado',
        'ano' => 'Este año',
    ] as $clave => $texto)
        <a href="{{ route('panel.documentos.facturas', ['atajo' => $clave]) }}"
           @class(['atajo', 'atajo--activo' => ($atajo ?? '') === $clave])>
            {{ $texto }}
        </a>
    @endforeach
</div>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ $desde->toDateString() }}">
    </div>

    <div class="campo">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ $hasta->toDateString() }}">
    </div>

    <button type="submit" class="boton boton--secundario">Ver</button>
</form>

{{-- ---------- Cifras ---------- --}}
<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Facturado</span>
        <strong>{{ $euros($tickets->sum('total')) }}</strong>
    </div>
    <div class="cifra">
        <span>Base</span>
        <strong>{{ $euros($tickets->sum('base')) }}</strong>
    </div>
    <div class="cifra">
        <span>Impuesto</span>
        <strong>{{ $euros($tickets->sum('impuesto')) }}</strong>
    </div>
    <div class="cifra">
        <span>Documentos</span>
        <strong>{{ $tickets->count() }}</strong>
    </div>
</div>

@if ($tickets->isEmpty())
    <div class="vacio">
        <h3>No se emitió nada en esas fechas</h3>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Número</th><th>Fecha</th><th>Cliente</th>
                        <th class="num">Base</th><th class="num">Impuesto</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->referencia() }}</td>
                        <td>{{ $ticket->fecha->format('d/m/Y H:i') }}</td>
                        <td>{{ $ticket->cliente?->nombreCompleto() ?? 'Cliente contado' }}</td>
                        <td class="num">{{ $euros($ticket->base) }}</td>
                        <td class="num">{{ $euros($ticket->impuesto) }}</td>
                        <td class="num"><strong>{{ $euros($ticket->total) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- ---------- Envío ---------- --}}
<div class="modal" id="modalEnvio" hidden>
    <div class="modal__caja" style="max-width:440px">
        <h2>Enviar por correo</h2>

        <form method="POST" action="{{ route('panel.documentos.facturas.enviar') }}">
            @csrf
            <input type="hidden" name="desde" value="{{ $desde->toDateString() }}">
            <input type="hidden" name="hasta" value="{{ $hasta->toDateString() }}">

            <div class="campo">
                <label for="emailEnvio">Dirección de correo</label>
                <input type="email" id="emailEnvio" name="email" required
                       placeholder="asesoria@ejemplo.com">
                <p class="campo__pista">
                    Se manda el PDF adjunto, con las mismas fechas que ves ahora.
                </p>
            </div>

            <div class="modal__pie">
                <button type="button" class="boton boton--secundario"
                        onclick="cerrarEnvio()">Cancelar</button>
                <button type="submit" class="boton">Enviar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/produccion.css') }}?v=34">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=34">
<link rel="stylesheet" href="{{ asset('css/claves.css') }}?v=34">
<script>
function abrirEnvio()  { document.getElementById('modalEnvio').hidden = false; }
function cerrarEnvio() { document.getElementById('modalEnvio').hidden = true; }

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarEnvio(); });
</script>
@endpush

@endsection
