@extends('panel.app')

@section('titulo', 'Cita ' . $reserva->codigo)

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $reserva->cliente_nombre }}</h1>
        <p>
            {{ $reserva->codigo }} ·
            {{ $reserva->fecha->locale('es')->isoFormat('dddd D [de] MMMM') }} ·
            {{ substr($reserva->hora_ini, 0, 5) }}–{{ substr($reserva->hora_fin, 0, 5) }}
        </p>
    </div>
    <a href="{{ route('panel.agenda', ['fecha' => $reserva->fecha->toDateString()]) }}"
       class="boton boton--secundario">Volver</a>
</div>

<div class="tarjeta" style="max-width:720px">
    <h2>Estado</h2>

    <p style="margin-bottom:1rem">
        <span class="etiqueta" style="background: {{ $reserva->color() }}33; color: {{ $reserva->color() }}">
            {{ ucfirst(strtolower(str_replace('_', ' ', $reserva->estado))) }}
        </span>
        <span style="color:var(--suave);font-size:.82rem;margin-left:.5rem">
            Origen: {{ ucfirst(strtolower($reserva->origen)) }}
        </span>
    </p>

    @if ($reserva->estado === 'PENDIENTE')
        <p class="aviso aviso--pendiente">
            Reserva recibida por el portal. El hueco está retenido hasta que la confirmes o la rechaces.
        </p>
    @endif

    @if ($reserva->motivo_rechazo)
        <p class="aviso aviso--error">Rechazada: {{ $reserva->motivo_rechazo }}</p>
    @endif

    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        @php $estado = $reserva->estado; @endphp

        @if ($estado === 'PENDIENTE')
            <form method="POST" action="{{ route('panel.agenda.cita.estado', $reserva) }}">
                @csrf
                <input type="hidden" name="accion" value="confirmar">
                <button type="submit" class="boton">Confirmar</button>
            </form>

            <button type="button" class="boton boton--secundario"
                    onclick="document.getElementById('formRechazo').hidden = false">Rechazar</button>
        @endif

        @if (in_array($estado, ['CONFIRMADA', 'EN_CURSO']))
            <form method="POST" action="{{ route('panel.agenda.cita.estado', $reserva) }}">
                @csrf
                <input type="hidden" name="accion" value="{{ $estado === 'CONFIRMADA' ? 'en_curso' : 'atendida' }}">
                <button type="submit" class="boton">
                    {{ $estado === 'CONFIRMADA' ? 'Marcar en curso' : 'Marcar atendida' }}
                </button>
            </form>

            <form method="POST" action="{{ route('panel.agenda.cita.estado', $reserva) }}"
                  onsubmit="return confirm('¿Marcar como plantón? Se anotará en la ficha del cliente.')">
                @csrf
                <input type="hidden" name="accion" value="no_show">
                <button type="submit" class="boton boton--secundario">No se presentó</button>
            </form>
        @endif

        @if ($reserva->estaAbierta())
            <form method="POST" action="{{ route('panel.agenda.cita.estado', $reserva) }}"
                  onsubmit="return confirm('¿Cancelar esta cita?')">
                @csrf
                <input type="hidden" name="accion" value="cancelar">
                <button type="submit" class="boton boton--secundario">Cancelar cita</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('panel.agenda.cita.estado', $reserva) }}"
          id="formRechazo" hidden style="margin-top:1rem">
        @csrf
        <input type="hidden" name="accion" value="rechazar">
        <div class="campo">
            <label for="motivo">Motivo del rechazo</label>
            <input type="text" id="motivo" name="motivo" required
                   placeholder="No hay disponibilidad para ese servicio...">
            <p class="campo__pista">Se lo enviaremos al cliente por email.</p>
        </div>
        <button type="submit" class="boton boton--peligro">Rechazar reserva</button>
    </form>
</div>

<div class="tarjeta" style="max-width:720px">
    <h2>Servicios</h2>

    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Servicio</th>
                    <th>Profesional</th>
                    <th class="num">Duración</th>
                    <th class="num">Precio</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($reserva->lineas as $linea)
                <tr>
                    <td>{{ substr($linea->hora_ini, 0, 5) }}</td>
                    <td>
                        {{ $linea->nombre_servicio }}
                        @if ($linea->tiempo_pausa_min > 0)
                            <div style="color:var(--suave);font-size:.72rem">
                                {{ $linea->duracion_min }}' activo +
                                {{ $linea->tiempo_pausa_min }}' pausa +
                                {{ $linea->tiempo_final_min }}' final
                            </div>
                        @endif
                    </td>
                    <td>{{ $linea->usuario?->nombre ?? '—' }}</td>
                    <td class="num">{{ $linea->duracionTotal() }} min</td>
                    <td class="num">{{ number_format($linea->precio, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="num">Total</th>
                    <th class="num">{{ number_format($reserva->importe_total, 2, ',', '.') }} €</th>
                </tr>
                @if ($reserva->importe_pagado > 0)
                    <tr>
                        <th colspan="4" class="num">Pagado por adelantado</th>
                        <th class="num">{{ number_format($reserva->importe_pagado, 2, ',', '.') }} €</th>
                    </tr>
                @endif
            </tfoot>
        </table>
    </div>
</div>

<div class="tarjeta" style="max-width:720px">
    <h2>Cliente</h2>

    <div class="rejilla-campos">
        <div>
            <p style="font-size:.75rem;color:var(--suave)">Nombre</p>
            <p>{{ $reserva->cliente_nombre }}</p>
        </div>
        <div>
            <p style="font-size:.75rem;color:var(--suave)">Teléfono</p>
            <p>{{ $reserva->cliente_telefono ?: '—' }}</p>
        </div>
        <div>
            <p style="font-size:.75rem;color:var(--suave)">Email</p>
            <p>{{ $reserva->cliente_email ?: '—' }}</p>
        </div>
    </div>

    @if ($reserva->cliente && $reserva->cliente->no_shows > 0)
        <p class="aviso aviso--pendiente" style="margin-top:1rem">
            Este cliente acumula {{ $reserva->cliente->no_shows }} plantón(es).
        </p>
    @endif

    @if ($reserva->notas_cliente)
        <div class="campo" style="margin-top:1rem">
            <label>Notas del cliente</label>
            <p style="font-size:.88rem">{{ $reserva->notas_cliente }}</p>
        </div>
    @endif
</div>

@endsection
