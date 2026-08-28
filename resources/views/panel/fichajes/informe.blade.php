@extends('panel.app')

@section('titulo', 'Registro de jornada')

@php
    $horas = fn ($m) => \App\Services\GestorFichajes::horasYMinutos($m);
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Registro de jornada</h1>
        <p>Control horario del personal</p>
    </div>
    <a href="{{ route('panel.fichajes') }}" class="boton boton--secundario">Mi jornada</a>
</div>

<p class="aviso aviso--info">
    Este registro es <strong>obligatorio</strong> y hay que conservarlo cuatro años
    a disposición de los trabajadores y de la Inspección de Trabajo.
    Los fichajes no se editan: las correcciones quedan registradas con su motivo.
</p>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="usuario_id">Trabajador</label>
        <select id="usuario_id" name="usuario_id" onchange="this.form.submit()">
            @foreach ($usuarios as $u)
                <option value="{{ $u->id }}" @selected($usuario?->id === $u->id)>{{ $u->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="campo">
        <label for="mes">Mes</label>
        <select id="mes" name="mes" onchange="this.form.submit()">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" @selected($mes === $m)>
                    {{ \Illuminate\Support\Carbon::create(null, $m, 1)->locale('es')->isoFormat('MMMM') }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="campo">
        <label for="ano">Año</label>
        <select id="ano" name="ano" onchange="this.form.submit()">
            @foreach (range(now()->year, now()->year - 4) as $a)
                <option value="{{ $a }}" @selected($ano === $a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>

    @if ($usuario)
        <a href="{{ route('panel.fichajes.exportar', ['usuario_id' => $usuario->id, 'mes' => $mes, 'ano' => $ano]) }}"
           class="boton boton--secundario">Exportar CSV</a>
    @endif
</form>

@if (! $resumen)
    <div class="vacio">
        <h3>Nadie tiene el control horario activado</h3>
        <p>Se activa en la ficha de cada usuario, en Ajustes.</p>
    </div>
@else
    <div class="cifras">
        <div class="cifra cifra--principal">
            <span>Horas del mes</span>
            <strong>{{ $horas($resumen['total_minutos']) }}</strong>
        </div>
        <div class="cifra">
            <span>Días trabajados</span>
            <strong>{{ $resumen['dias_trabajados'] }}</strong>
        </div>
        <div class="cifra">
            <span>Media diaria</span>
            <strong>{{ $horas($resumen['media_diaria']) }}</strong>
        </div>
        <div class="cifra">
            <span>Previsto por horario</span>
            <strong>{{ $horas($resumen['total_previsto'] ?? 0) }}</strong>
        </div>
        <div class="cifra {{ ($resumen['desviacion'] ?? 0) != 0 ? 'cifra--alerta' : '' }}">
            <span>Desviación</span>
            <strong>
                {{ ($resumen['desviacion'] ?? 0) >= 0 ? '+' : '−' }}{{ $horas(abs($resumen['desviacion'] ?? 0)) }}
            </strong>
        </div>
        @if (($resumen['total_extra'] ?? 0) > 0)
            <div class="cifra">
                <span>Fuera de horario</span>
                <strong>{{ $horas($resumen['total_extra']) }}</strong>
            </div>
        @endif
        <div class="cifra {{ $resumen['dias_incompletos'] > 0 ? 'cifra--alerta' : '' }}">
            <span>Días con incidencias</span>
            <strong>{{ $resumen['dias_incompletos'] }}</strong>
        </div>
    </div>

    @if (($resumen['total_previsto'] ?? 0) === 0)
        <p class="aviso aviso--info">
            Esta persona no tiene horario configurado, así que no hay con qué
            comparar. Se define en <a href="{{ route('panel.agenda.horarios') }}" class="enlace">Horarios</a>.
        </p>
    @elseif (($resumen['desviacion'] ?? 0) > 300)
        <p class="aviso aviso--pendiente">
            Lleva <strong>{{ $horas($resumen['desviacion']) }}</strong> por encima de su
            horario. Conviene mirarlo antes de que se acumule: las horas de más
            se compensan o se pagan, pero no desaparecen.
        </p>
    @endif

    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Día</th><th>Fichajes</th>
                        <th class="num">Pausa</th><th class="num">Previsto</th>
                        <th class="num">Real</th><th class="num">Dif.</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($resumen['dias'] as $dia)
                    @continue(! $dia['trabajado'] && empty($dia['ausencia']))

                    <tr @class(['fila-incidencia' => $dia['incompleta']])>
                        <td>
                            <strong>{{ $dia['fecha']->format('d/m') }}</strong>
                            <div style="color:var(--suave);font-size:.72rem">
                                {{ $dia['fecha']->locale('es')->isoFormat('dddd') }}
                            </div>
                        </td>
                        <td>
                            <div class="fichajes-linea">
                                @foreach ($dia['fichajes'] as $fichaje)
                                    <span class="fichaje-pastilla fichaje-pastilla--{{ strtolower($fichaje->tipo) }}"
                                          title="{{ $fichaje->etiqueta() }}{{ $fichaje->esManual() ? ' (manual)' : '' }}">
                                        {{ $fichaje->hora() }}
                                        @if ($fichaje->esManual())·@endif
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="num">{{ $dia['pausa'] ?: '—' }}</td>
                        <td class="num" style="color:var(--suave)">
                            {{ ($dia['previstos'] ?? 0) > 0
                               ? number_format($dia['previstos'] / 60, 2, ',', '') : '—' }}
                        </td>
                        <td class="num">
                            {{ $dia['minutos'] > 0 ? number_format($dia['minutos'] / 60, 2, ',', '') : '—' }}
                        </td>
                        <td class="num">
                            @php $dif = $dia['desviacion'] ?? 0; @endphp

                            @if ($dif != 0)
                                <span @style([
                                    'color: var(--error)' => $dif < -15,
                                    'color: var(--aviso)' => $dif > 15,
                                ])>
                                    {{ $dif > 0 ? '+' : '−' }}{{ number_format(abs($dif) / 60, 2, ',', '') }}
                                </span>
                            @elseif (($dia['extra'] ?? 0) > 0)
                                <span style="color:var(--aviso)" title="Fuera de su horario">
                                    +{{ number_format($dia['extra'] / 60, 2, ',', '') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($dia['incompleta'])
                                <span class="etiqueta etiqueta--inactivo">incompleto</span>
                            @elseif ($dia['ausencia'] ?? null)
                                <span class="etiqueta">{{ $dia['ausencia']->etiqueta() }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ---------- Añadir fichaje olvidado ---------- --}}
    <div class="tarjeta" style="max-width:720px">
        <h2>Añadir un fichaje olvidado</h2>
        <p class="tarjeta__ayuda">
            Quedará marcado como <strong>manual</strong> y con el motivo.
            La Inspección mira esto: un registro lleno de fichajes manuales
            pierde credibilidad, así que conviene que sea la excepción.
        </p>

        <form method="POST" action="{{ route('panel.fichajes.anadir') }}">
            @csrf
            <input type="hidden" name="usuario_id" value="{{ $usuario->id }}">

            <div class="rejilla-campos">
                <div class="campo">
                    <label for="tipo">Qué</label>
                    <select id="tipo" name="tipo" required>
                        @foreach (\App\Models\Fichaje::TIPOS as $clave => $texto)
                            <option value="{{ $clave }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="fecha_hora">Cuándo</label>
                    <input type="datetime-local" id="fecha_hora" name="fecha_hora" required>
                </div>

                <div class="campo">
                    <label for="motivo">Motivo *</label>
                    <input type="text" id="motivo" name="motivo" required maxlength="300"
                           placeholder="Olvidó fichar la salida">
                </div>
            </div>

            <button type="submit" class="boton boton--pequeno">Añadir fichaje</button>
        </form>
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/fichajes.css') }}?v=19">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=19">
@endpush

@endsection
