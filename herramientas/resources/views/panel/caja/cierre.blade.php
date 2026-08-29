@extends('panel.app')

@section('titulo', 'Cierre del ' . $cierre->fecha_fin->format('d/m/Y'))

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Cierre de jornada</h1>
        <p>
            {{ $cierre->fecha_ini->format('d/m/Y H:i') }} →
            {{ $cierre->fecha_fin->format('d/m/Y H:i') }} ·
            {{ $cierre->usuario?->nombre }}
        </p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="{{ route('panel.documentos.cierre.pdf', $cierre) }}"
           class="boton boton--secundario">Descargar PDF</a>

        <button type="button" class="boton boton--secundario"
                onclick="abrirEnvio()">Enviar por correo</button>

        <button type="button" class="boton boton--secundario"
                onclick="abrirImprimir()">Imprimir</button>

        <a href="{{ route('panel.caja') }}" class="boton boton--secundario">Volver</a>
    </div>
</div>

{{-- ---------- Enviar ---------- --}}
<div class="modal" id="modalEnvio" hidden>
    <div class="modal__caja" style="max-width:440px">
        <h2>Enviar el cierre</h2>

        <form method="POST" action="{{ route('panel.documentos.cierre.enviar', $cierre) }}">
            @csrf

            <div class="campo">
                <label for="emailEnvio">Dirección de correo</label>
                <input type="email" id="emailEnvio" name="email" required
                       placeholder="asesoria@ejemplo.com">
                <p class="campo__pista">Va el PDF adjunto.</p>
            </div>

            <div class="modal__pie">
                <button type="button" class="boton boton--secundario"
                        onclick="cerrarEnvio()">Cancelar</button>
                <button type="submit" class="boton">Enviar</button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- Imprimir ---------- --}}
<div class="modal" id="modalImprimir" hidden>
    <div class="modal__caja" style="max-width:440px">
        <h2>Imprimir</h2>

        <p class="tarjeta__ayuda">
            Sale por la impresora de tickets. El parte de trabajo va en papel
            aparte, con lo que ha facturado cada profesional.
        </p>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.25rem">
            <form method="POST" action="{{ route('panel.caja.reimprimir', $cierre) }}">
                @csrf
                <button type="submit" class="boton">Cierre de caja</button>
            </form>

            <form method="POST" action="{{ route('panel.caja.reimprimir', $cierre) }}">
                @csrf
                <input type="hidden" name="que" value="parte">
                <button type="submit" class="boton boton--secundario">Parte de trabajo</button>
            </form>
        </div>

        <div class="modal__pie">
            <button type="button" class="boton boton--secundario"
                    onclick="cerrarImprimir()">Cerrar</button>
        </div>
    </div>
</div>

@if ($cierre->hayDescuadre())
    <p class="aviso aviso--error">
        Descuadre de {{ number_format($cierre->descuadre, 2, ',', '.') }} €
        ({{ $cierre->descuadre > 0 ? 'sobraba' : 'faltaba' }} dinero en el cajón).
    </p>
@endif

<div class="tarjeta">
    <h2>Totales</h2>
    <div class="arqueo">
        <div class="arqueo__dato arqueo__dato--destacado">
            <span>Ventas</span>
            <strong>{{ number_format($cierre->total_ventas, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Base</span>
            <strong>{{ number_format($cierre->total_base, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Impuesto</span>
            <strong>{{ number_format($cierre->total_impuesto, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Tickets</span>
            <strong>{{ $cierre->num_tickets }}</strong>
        </div>
        <div class="arqueo__dato">
            <span>Ticket medio</span>
            <strong>{{ number_format($cierre->ticketMedio(), 2, ',', '.') }} €</strong>
        </div>
    </div>
</div>

<div class="tarjeta">
    <h2>Arqueo de efectivo</h2>
    <div class="arqueo">
        <div class="arqueo__dato">
            <span>Debía haber</span>
            <strong>{{ number_format($cierre->efectivo_teorico, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato">
            <span>Contado</span>
            <strong>{{ number_format($cierre->efectivo_contado, 2, ',', '.') }} €</strong>
        </div>
        <div class="arqueo__dato {{ $cierre->hayDescuadre() ? 'arqueo__dato--alerta' : '' }}">
            <span>Descuadre</span>
            <strong>{{ number_format($cierre->descuadre, 2, ',', '.') }} €</strong>
        </div>
    </div>

    @if ($cierre->observaciones)
        <p class="campo__pista" style="margin-top:1rem">{{ $cierre->observaciones }}</p>
    @endif
</div>

@foreach ([
    'Por medio de pago'  => $cierre->totales_por_medio,
    'Por familia'        => $cierre->totales_por_familia,
    'Por profesional'    => $cierre->totales_por_profesional,
] as $titulo => $datos)
    @if (! empty($datos))
        <div class="tarjeta">
            <h2>{{ $titulo }}</h2>
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <tbody>
                    @foreach ($datos as $clave => $importe)
                        <tr>
                            <td>{{ \App\Models\TicketCobro::MEDIOS[$clave] ?? $clave }}</td>
                            <td class="num">{{ number_format($importe, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

<div class="tarjeta">
    <h2>Tickets incluidos</h2>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr><th>Documento</th><th>Hora</th><th>Cobro</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
            @foreach ($tickets as $ticket)
                <tr>
                    <td>{{ $ticket->referencia() }}</td>
                    <td>{{ $ticket->fecha->format('H:i') }}</td>
                    <td>{{ $ticket->medios() }}</td>
                    <td class="num">{{ number_format($ticket->total, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/claves.css') }}?v=34">
<script>
function abrirEnvio()     { document.getElementById('modalEnvio').hidden = false; }
function cerrarEnvio()    { document.getElementById('modalEnvio').hidden = true; }
function abrirImprimir()  { document.getElementById('modalImprimir').hidden = false; }
function cerrarImprimir() { document.getElementById('modalImprimir').hidden = true; }

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { cerrarEnvio(); cerrarImprimir(); }
});
</script>
@endpush

@endsection
