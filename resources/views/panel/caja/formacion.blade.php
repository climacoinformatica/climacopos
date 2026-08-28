@extends('panel.app')

@section('titulo', 'Documentos de formación')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Documentos de formación</h1>
        <p>{{ $tickets->total() }} documento(s) · {{ number_format($total, 2, ',', '.') }} € en prácticas</p>
    </div>
    <a href="{{ route('panel.caja') }}" class="boton boton--secundario">Volver a caja</a>
</div>

<p class="aviso aviso--info">
    Estos documentos <strong>no tienen valor fiscal</strong>. No entran en el cierre de jornada,
    no suman en informes ni en comisiones, y no consumen numeración fiscal: llevan su
    propia serie <code>FOR</code>. Están aquí solo para consulta y para que los borres
    cuando ya no te sirvan.
</p>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}">
    </div>
    <div class="campo">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
    </div>
    <div class="campo">
        <label for="usuario_id">Empleado</label>
        <select id="usuario_id" name="usuario_id">
            <option value="">Todos</option>
            @foreach ($usuarios as $usuario)
                <option value="{{ $usuario->id }}" @selected(($filtros['usuario_id'] ?? null) == $usuario->id)>
                    {{ $usuario->nombre }}
                </option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="boton boton--secundario">Filtrar</button>
    <a href="{{ route('panel.caja.formacion.exportar') }}" class="boton boton--secundario">
        Exportar todo
    </a>
</form>

@if ($tickets->isEmpty())
    <div class="vacio">
        <h3>No hay documentos de formación</h3>
        <p>Aparecerán aquí cuando un empleado marcado como «en formación» realice cobros.</p>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Documento</th><th>Fecha</th><th>Empleado</th>
                        <th>Artículos</th><th>Cobro</th><th class="num">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td><strong>{{ $ticket->referencia() }}</strong></td>
                        <td>{{ $ticket->fecha->format('d/m/Y H:i') }}</td>
                        <td>{{ $ticket->usuario?->nombre ?? '—' }}</td>
                        <td>
                            <small style="color:var(--suave)">
                                {{ $ticket->lineas->pluck('descripcion')->take(3)->join(', ') }}
                                @if ($ticket->lineas->count() > 3)
                                    y {{ $ticket->lineas->count() - 3 }} más
                                @endif
                            </small>
                        </td>
                        <td>{{ $ticket->medios() ?: '—' }}</td>
                        <td class="num">{{ number_format($ticket->total, 2, ',', '.') }} €</td>
                        <td>
                            @if ($usuarioSalon->tienePermiso(\App\Support\Permisos::FORMACION_BORRAR))
                                <form method="POST" action="{{ route('panel.caja.formacion.borrar') }}"
                                      onsubmit="return confirm('¿Borrar {{ $ticket->referencia() }}?')">
                                    @csrf
                                    <input type="hidden" name="ambito" value="UNO">
                                    <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
                                    <button type="submit" class="boton boton--secundario boton--pequeno">
                                        Borrar
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $tickets->links() }}

    @if ($usuarioSalon->tienePermiso(\App\Support\Permisos::FORMACION_BORRAR))
        <div class="tarjeta" style="margin-top:1.5rem">
            <h2>Borrado en bloque</h2>
            <p class="tarjeta__ayuda">
                Antes de borrar, conviene exportar: el borrado es definitivo y queda
                registrado en la auditoría.
            </p>

            <form method="POST" action="{{ route('panel.caja.formacion.borrar') }}"
                  onsubmit="return confirm('Esto borra documentos de formación de forma DEFINITIVA. ¿Continuar?')">
                @csrf
                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="ambito">Qué borrar</label>
                        <select id="ambito" name="ambito" required>
                            <option value="RANGO">Un rango de fechas</option>
                            <option value="TODO">Todos los documentos</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="desde_borrar">Desde</label>
                        <input type="date" id="desde_borrar" name="desde">
                    </div>
                    <div class="campo">
                        <label for="hasta_borrar">Hasta</label>
                        <input type="date" id="hasta_borrar" name="hasta">
                    </div>
                </div>
                <button type="submit" class="boton boton--peligro boton--pequeno">
                    Borrar definitivamente
                </button>
            </form>
        </div>
    @endif
@endif

@endsection
