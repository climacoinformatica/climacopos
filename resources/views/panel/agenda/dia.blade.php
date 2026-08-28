@extends('panel.app')

@section('titulo', 'Agenda')

@php
    use App\Support\Intervalo;

    $minIni = Intervalo::aMinutos($horaIni);
    $minFin = Intervalo::aMinutos($horaFin);

    // Píxeles por minuto. Con 1,6 una cita de 20 minutos mide 32 px,
    // que es lo mínimo para que quepa una línea de texto legible.
    $altura = 1.6;
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Agenda</h1>
        <p>{{ $fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}</p>
    </div>

    <div class="agenda__controles">
        <a href="{{ route('panel.agenda', ['fecha' => $fecha->copy()->subDay()->toDateString()]) }}"
           class="boton boton--secundario">&larr;</a>
        <a href="{{ route('panel.agenda', ['fecha' => now()->toDateString()]) }}"
           class="boton boton--secundario">Hoy</a>
        <a href="{{ route('panel.agenda', ['fecha' => $fecha->copy()->addDay()->toDateString()]) }}"
           class="boton boton--secundario">&rarr;</a>
        <input type="date" value="{{ $fecha->toDateString() }}" id="selectorFecha" class="agenda__fecha">
        <a href="{{ route('panel.agenda.cita.nueva', ['fecha' => $fecha->toDateString()]) }}"
           class="boton">Nueva cita</a>
    </div>
</div>

@if ($pendientes > 0)
    <p class="aviso aviso--pendiente">
        Hay {{ $pendientes }} reserva(s) online pendientes de confirmar.
        Las verás con un punto naranja parpadeante.
    </p>
@endif

@if ($columnas === [])
    <div class="vacio">
        <h3>No hay profesionales dados de alta</h3>
        <p>La agenda necesita al menos un usuario marcado como profesional.</p>
        <a href="{{ route('panel.agenda.horarios') }}" class="boton">Configurar horarios</a>
    </div>
@else

<div class="agenda">
    <div class="agenda__horas">
        <div class="agenda__cabecera-hueco"></div>
        @for ($m = $minIni; $m <= $minFin; $m += 30)
            <div class="agenda__hora" style="top: {{ ($m - $minIni) * $altura }}px">
                {{ Intervalo::aHora($m) }}
            </div>
        @endfor
    </div>

    <div class="agenda__columnas">
        @foreach ($columnas as $columna)
            @php $profesional = $columna['profesional']; @endphp
            <div class="agenda__columna">
                <div class="agenda__cabecera">
                    <span class="agenda__avatar" style="--color: {{ $profesional->color_agenda }}">
                        {{ $profesional->iniciales() }}
                    </span>
                    <div>
                        <strong>{{ $profesional->alias ?: $profesional->nombre }}</strong>
                        <small>{{ $columna['ocupacion'] }}% ocupado · {{ count($columna['citas']) }} cita(s)</small>
                    </div>
                </div>

                <div class="agenda__pista" style="height: {{ ($minFin - $minIni) * $altura }}px"
                     data-usuario="{{ $profesional->id }}">

                    @for ($m = $minIni; $m <= $minFin; $m += 30)
                        <div class="agenda__linea {{ $m % 60 === 0 ? 'agenda__linea--hora' : '' }}"
                             style="top: {{ ($m - $minIni) * $altura }}px"></div>
                    @endfor

                    @php
                        $libres = (new Intervalo($minIni, $minFin))->menos($columna['jornada']);
                    @endphp
                    @foreach ($libres as $fuera)
                        <div class="agenda__fuera"
                             style="top: {{ ($fuera->ini - $minIni) * $altura }}px;
                                    height: {{ $fuera->duracion() * $altura }}px"></div>
                    @endforeach

                    @foreach ($columna['bloqueos'] as $bloqueo)
                        @php
                            $bIni = Intervalo::aMinutos($bloqueo->hora_ini);
                            $bFin = Intervalo::aMinutos($bloqueo->hora_fin);
                        @endphp
                        <div class="agenda__bloqueo"
                             style="top: {{ ($bIni - $minIni) * $altura }}px;
                                    height: {{ ($bFin - $bIni) * $altura }}px">
                            {{ $bloqueo->motivo ?: 'Bloqueado' }}
                        </div>
                    @endforeach

                    {{-- Citas --}}
                    @foreach ($columna['citas'] as $cita)
                        @php
                            $reserva = $cita['reserva'];
                            $top  = ($cita['ini'] - $minIni) * $altura;
                            $alto = $cita['duracion'] * $altura;

                            // El contenido se ajusta al alto disponible
                            $tamano = match (true) {
                                $cita['duracion'] < 30 => 'cita--corta',
                                $cita['duracion'] < 45 => 'cita--media',
                                default                => '',
                            };
                        @endphp
                        <a href="{{ route('panel.agenda.cita', $reserva) }}"
                           class="cita cita--{{ strtolower($reserva->estado) }} {{ $tamano }}"
                           style="top: {{ $top }}px;
                                  height: {{ $alto }}px;
                                  --color: {{ $reserva->color() }}"
                           title="{{ substr($cita['linea']->hora_ini, 0, 5) }} · {{ $reserva->cliente_nombre }} · {{ $cita['linea']->nombre_servicio }}{{ $reserva->estado === 'PENDIENTE' ? ' · SIN CONFIRMAR' : '' }}">

                            @if ($cita['pausa'] > 0)
                                <span class="cita__pausa"
                                      style="top: {{ $cita['activa'] * $altura }}px;
                                             height: {{ $cita['pausa'] * $altura }}px"></span>
                            @endif

                            <span class="cita__hora">
                                {{ substr($cita['linea']->hora_ini, 0, 5) }}
                                @if ($cita['pausa'] > 0 && $cita['duracion'] >= 45)
                                    · {{ $cita['activa'] }}+{{ $cita['pausa'] }}'
                                @endif
                            </span>
                            <span class="cita__cliente">{{ $reserva->cliente_nombre }}</span>
                            <span class="cita__servicio">{{ $cita['linea']->nombre_servicio }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<p class="agenda__leyenda">
    <span><i class="punto"></i> Sin confirmar</span>
    <span><i style="background:#6366f1"></i> Confirmada</span>
    <span><i style="background:#10b981"></i> En curso</span>
    <span><i style="background:#64748b"></i> Atendida</span>
    <span><i class="rayado"></i> Pausa: el profesional queda libre</span>
</p>

@endif

@push('scripts')
<script>
document.getElementById('selectorFecha')?.addEventListener('change', function () {
    window.location = '{{ route('panel.agenda') }}?fecha=' + this.value;
});

document.querySelectorAll('.agenda__pista').forEach(function (pista) {
    pista.addEventListener('click', function (evento) {
        if (evento.target.closest('.cita') || evento.target.closest('.agenda__bloqueo')) return;

        const y = evento.clientY - pista.getBoundingClientRect().top;
        const minutos = {{ $minIni }} + Math.floor(y / {{ $altura }} / 15) * 15;
        const hora = String(Math.floor(minutos / 60)).padStart(2, '0') + ':' +
                     String(minutos % 60).padStart(2, '0');

        window.location = '{{ route('panel.agenda.cita.nueva') }}' +
            '?fecha={{ $fecha->toDateString() }}&hora=' + hora +
            '&usuario_id=' + pista.dataset.usuario;
    });
});
</script>
@endpush

@endsection
