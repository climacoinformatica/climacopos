@extends('panel.app')

@section('titulo', 'Mi registro de jornada')

@php
    $horas = fn ($m) => \App\Services\GestorFichajes::horasYMinutos($m);
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Mi registro de jornada</h1>
        <p>{{ $usuario->nombre }}</p>
    </div>
    <a href="{{ route('panel.fichajes') }}" class="boton boton--secundario">Fichar</a>
</div>

<p class="aviso aviso--info">
    Tienes derecho a consultar y descargar tu propio registro cuando quieras,
    sin pedírselo a nadie. La empresa está obligada a conservarlo cuatro años.
</p>

<form method="GET" class="filtros">
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

    <a href="{{ route('panel.fichajes.mio.exportar', ['mes' => $mes, 'ano' => $ano]) }}"
       class="boton">Descargar</a>
</form>

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
    @if (($resumen['dias_ausencia'] ?? 0) > 0)
        <div class="cifra">
            <span>Días de ausencia</span>
            <strong>{{ $resumen['dias_ausencia'] }}</strong>
        </div>
    @endif
    <div class="cifra {{ $resumen['dias_incompletos'] > 0 ? 'cifra--alerta' : '' }}">
        <span>Con incidencias</span>
        <strong>{{ $resumen['dias_incompletos'] }}</strong>
    </div>
</div>

@if ($resumen['dias_incompletos'] > 0)
    <p class="aviso aviso--pendiente">
        Hay {{ $resumen['dias_incompletos'] }} día(s) con algún fichaje que falta.
        Díselo a tu responsable: puede añadirlo dejando constancia de que se hizo
        a mano y por qué.
    </p>
@endif

<div class="tarjeta" style="padding:.5rem">
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Día</th><th>Fichajes</th>
                    <th class="num">Pausa</th><th class="num">Horas</th><th></th>
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
                        @if (! $dia['trabajado'] && $dia['ausencia'])
                            <span class="etiqueta">{{ $dia['ausencia']->etiqueta() }}</span>
                        @else
                            <div class="fichajes-linea">
                                @foreach ($dia['fichajes'] as $fichaje)
                                    <span class="fichaje-pastilla fichaje-pastilla--{{ strtolower($fichaje->tipo) }}"
                                          title="{{ $fichaje->etiqueta() }}{{ $fichaje->esManual() ? ' (añadido a mano)' : '' }}">
                                        {{ $fichaje->hora() }}@if ($fichaje->esManual())·@endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="num">{{ $dia['pausa'] ?: '—' }}</td>
                    <td class="num">
                        {{ $dia['minutos'] > 0 ? number_format($dia['minutos'] / 60, 2, ',', '') : '—' }}
                    </td>
                    <td>
                        @if ($dia['incompleta'])
                            <span class="etiqueta etiqueta--inactivo">falta un fichaje</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/fichajes.css') }}?v=18">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=18">
@endpush

@endsection
