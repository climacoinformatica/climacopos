@extends('panel.app')

@section('titulo', 'Tickets')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Tickets</h1>
        <p>{{ $tickets->total() }} documento(s)</p>
    </div>
    <div style="display:flex;gap:.5rem;align-items:center">
        <input type="date" value="{{ $fecha }}" id="selectorFechaTickets" class="agenda__fecha">
        <a href="{{ route('panel.tpv') }}" class="boton">Ir al TPV</a>
    </div>
</div>

@if ($tickets->isEmpty())
    <div class="vacio">
        <h3>No hay tickets ese día</h3>
        <p>Prueba con otra fecha.</p>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Documento</th><th>Hora</th><th>Usuario</th>
                        <th>Cobro</th><th>Estado</th><th class="num">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td>
                            <strong>{{ $ticket->referencia() }}</strong>
                            @if ($ticket->esRectificativa())
                                <div style="color:var(--suave);font-size:.72rem">
                                    rectifica {{ $ticket->rectificaA?->referencia() }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $ticket->fecha->format('H:i') }}</td>
                        <td>{{ $ticket->usuario?->nombre }}</td>
                        <td>{{ $ticket->medios() ?: '—' }}</td>
                        <td>
                            <span class="etiqueta {{ $ticket->estado === 'ANULADO' ? 'etiqueta--inactivo' : '' }}">
                                {{ ucfirst(strtolower($ticket->estado)) }}
                            </span>
                            @if ($ticket->cierre_id)
                                <span class="etiqueta">Cerrado</span>
                            @endif
                        </td>
                        <td class="num">
                            {{ number_format($ticket->total, 2, ',', '.') }} €
                            @if ($ticket->tieneDevoluciones())
                                <div style="color:var(--error);font-size:.72rem">
                                    −{{ number_format($ticket->importeDevuelto(), 2, ',', '.') }} € devuelto
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($ticket->esDevolvible())
                                <a href="{{ route('panel.tpv.devolucion', $ticket) }}"
                                   class="boton boton--secundario boton--pequeno">Devolver</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $tickets->links() }}
@endif

@push('scripts')
<script>
document.getElementById('selectorFechaTickets').addEventListener('change', function () {
    window.location = '{{ route('panel.tpv.tickets') }}?fecha=' + this.value;
});
</script>
@endpush

@endsection
