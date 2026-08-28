@extends('panel.app')

@section('titulo', 'Calendario del equipo')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Calendario del equipo</h1>
        <p>{{ $calendario['desde']->locale('es')->isoFormat('MMMM [de] YYYY') }}</p>
    </div>
    <a href="{{ route('panel.ausencias') }}" class="boton boton--secundario">Mis ausencias</a>
</div>

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
            @foreach (range(now()->year - 1, now()->year + 1) as $a)
                <option value="{{ $a }}" @selected($ano === $a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>
</form>

@if (count($calendario['solapes']) > 0)
    <p class="aviso aviso--pendiente">
        Hay <strong>{{ count($calendario['solapes']) }} día(s)</strong> con dos o más
        personas ausentes a la vez. Conviene mirarlos antes de aprobar nada más:
        dejar el salón sin nadie un sábado es el error caro.
    </p>
@endif

<div class="tarjeta" style="padding:.5rem">
    <div class="calendario-envoltorio">
        <table class="calendario-equipo">
            <thead>
                <tr>
                    <th class="calendario-equipo__nombre">Persona</th>
                    @foreach ($calendario['filas'][0]['dias'] ?? [] as $dia)
                        <th @class(['calendario-equipo__finde' => $dia['finde'],
                                    'calendario-equipo__hoy' => $dia['fecha']->isToday()])>
                            <small>{{ $dia['fecha']->locale('es')->isoFormat('dd')[0] }}</small>
                            {{ $dia['fecha']->day }}
                        </th>
                    @endforeach
                    <th class="num">Quedan</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($calendario['filas'] as $fila)
                <tr>
                    <td class="calendario-equipo__nombre">
                        <span class="punto-color" style="background:{{ $fila['usuario']->color_agenda }}"></span>
                        {{ $fila['usuario']->nombre }}
                    </td>

                    @foreach ($fila['dias'] as $dia)
                        <td @class([
                                'calendario-equipo__finde' => $dia['finde'],
                                'calendario-equipo__hoy'   => $dia['fecha']->isToday(),
                            ])>
                            @if ($dia['ausencia'])
                                <span class="marca-ausencia marca-ausencia--{{ strtolower($dia['ausencia']->tipo) }}"
                                      title="{{ $dia['ausencia']->etiqueta() }}">
                                </span>
                            @endif
                        </td>
                    @endforeach

                    <td class="num">
                        {{ rtrim(rtrim(number_format($cupos[$fila['usuario']->id]['restantes'] ?? 0, 1, ',', ''), '0'), ',') }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="leyenda-ausencias">
        @foreach (['vacaciones' => 'Vacaciones', 'baja' => 'Baja',
                   'permiso' => 'Permiso', 'asuntos_propios' => 'Asuntos propios'] as $clave => $texto)
            <span>
                <i class="marca-ausencia marca-ausencia--{{ $clave }}"></i> {{ $texto }}
            </span>
        @endforeach
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/ausencias.css') }}?v=18">
@endpush

@endsection
