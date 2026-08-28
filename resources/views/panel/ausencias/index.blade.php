@extends('panel.app')

@section('titulo', 'Ausencias')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Vacaciones y ausencias</h1>
        <p>{{ $usuario->nombre }} · año {{ $cupo['ano'] }}</p>
    </div>
    @if ($gestiona)
        <a href="{{ route('panel.ausencias.calendario') }}" class="boton boton--secundario">
            Calendario del equipo
        </a>
    @endif
</div>

{{-- ---------- Cupo ---------- --}}
<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Días disponibles</span>
        <strong>{{ rtrim(rtrim(number_format($cupo['restantes'], 1, ',', ''), '0'), ',') }}</strong>
        @if ($cupo['solicitados'] > 0)
            <em><small>
                quedarían {{ rtrim(rtrim(number_format($cupo['proyectado'], 1, ',', ''), '0'), ',') }}
                si se aprueba lo pendiente
            </small></em>
        @endif
    </div>
    <div class="cifra">
        <span>Cupo anual</span>
        <strong>{{ rtrim(rtrim(number_format($cupo['total'], 1, ',', ''), '0'), ',') }}</strong>
    </div>
    <div class="cifra">
        <span>Disfrutados</span>
        <strong>{{ rtrim(rtrim(number_format($cupo['gastados'], 1, ',', ''), '0'), ',') }}</strong>
    </div>
    <div class="cifra">
        <span>Pendientes de aprobar</span>
        <strong>{{ rtrim(rtrim(number_format($cupo['solicitados'], 1, ',', ''), '0'), ',') }}</strong>
    </div>
</div>

{{-- ---------- Solicitudes por resolver ---------- --}}
@if ($pendientes->isNotEmpty())
    <div class="tarjeta">
        <h2>Solicitudes por resolver</h2>

        @foreach ($pendientes as $solicitud)
            <div class="solicitud">
                <div class="solicitud__datos">
                    <strong>{{ $solicitud->usuario?->nombre }}</strong>
                    <span class="etiqueta">{{ $solicitud->etiqueta() }}</span>
                    <div>
                        {{ $solicitud->resumenFechas() }}
                        @if ($solicitud->dias_computados > 0)
                            · {{ rtrim(rtrim(number_format($solicitud->dias_computados, 1, ',', ''), '0'), ',') }} día(s)
                        @endif
                    </div>
                    @if ($solicitud->motivo)
                        <small>{{ $solicitud->motivo }}</small>
                    @endif
                </div>

                <div class="solicitud__acciones">
                    <form method="POST" action="{{ route('panel.ausencias.aprobar', $solicitud) }}">
                        @csrf
                        <button type="submit" class="boton boton--pequeno">Aprobar</button>
                    </form>

                    <form method="POST" action="{{ route('panel.ausencias.rechazar', $solicitud) }}"
                          class="solicitud__rechazo">
                        @csrf
                        <input type="text" name="respuesta" placeholder="Motivo del rechazo" required
                               maxlength="300">
                        <button type="submit" class="boton boton--secundario boton--pequeno">
                            Rechazar
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- ---------- Solicitar ---------- --}}
<div class="tarjeta" style="max-width:760px">
    <h2>{{ $gestiona ? 'Registrar una ausencia' : 'Solicitar días' }}</h2>

    @unless ($gestiona)
        <p class="tarjeta__ayuda">
            La solicitud queda pendiente hasta que la revise un responsable.
            Mientras tanto, la agenda sigue ofreciendo tus huecos.
        </p>
    @endunless

    <form method="POST" action="{{ route('panel.ausencias.solicitar') }}">
        @csrf

        @if ($gestiona)
            <div class="campo">
                <label for="usuario_id">Persona</label>
                <select id="usuario_id" name="usuario_id">
                    @foreach (\App\Models\Usuario::activos()->orderBy('nombre')->get() as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $usuario->id)>{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="rejilla-campos">
            <div class="campo">
                <label for="tipo">Tipo *</label>
                <select id="tipo" name="tipo" required>
                    @foreach (\App\Models\Ausencia::TIPOS as $clave => $texto)
                        <option value="{{ $clave }}">{{ $texto }}</option>
                    @endforeach
                </select>
                <p class="campo__pista" id="avisoTipo"></p>
            </div>

            <div class="campo">
                <label for="desde">Desde *</label>
                <input type="date" id="desde" name="desde" required
                       value="{{ old('desde') }}">
            </div>

            <div class="campo">
                <label for="hasta">Hasta *</label>
                <input type="date" id="hasta" name="hasta" required
                       value="{{ old('hasta') }}">
            </div>

            <div class="campo">
                <label for="medio_dia">Medio día</label>
                <select id="medio_dia" name="medio_dia">
                    <option value="">Jornada completa</option>
                    <option value="MANANA">Solo la mañana</option>
                    <option value="TARDE">Solo la tarde</option>
                </select>
                <p class="campo__pista">Solo si es un único día.</p>
            </div>
        </div>

        <div class="campo">
            <label for="motivo">Motivo</label>
            <input type="text" id="motivo" name="motivo" maxlength="300">
        </div>

        <button type="submit" class="boton">
            {{ $gestiona ? 'Registrar' : 'Solicitar' }}
        </button>
    </form>
</div>

{{-- ---------- Historial ---------- --}}
<div class="tarjeta">
    <h2>Mis ausencias</h2>

    @if ($ausencias->isEmpty())
        <p class="campo__pista">Todavía no hay ninguna.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Tipo</th><th>Fechas</th><th class="num">Días</th>
                        <th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($ausencias as $ausencia)
                    <tr @class(['fila-anulada' => in_array($ausencia->estado, ['RECHAZADA', 'CANCELADA'])])>
                        <td>
                            {{ $ausencia->etiqueta() }}
                            @if ($ausencia->estaEnCurso())
                                <span class="etiqueta">en curso</span>
                            @endif
                        </td>
                        <td>{{ $ausencia->resumenFechas() }}</td>
                        <td class="num">
                            {{ $ausencia->dias_computados > 0
                               ? rtrim(rtrim(number_format($ausencia->dias_computados, 1, ',', ''), '0'), ',')
                               : '—' }}
                        </td>
                        <td>
                            <span class="etiqueta {{ in_array($ausencia->estado, ['RECHAZADA','CANCELADA']) ? 'etiqueta--inactivo' : '' }}">
                                {{ ucfirst(strtolower($ausencia->estado)) }}
                            </span>
                            @if ($ausencia->respuesta)
                                <div style="color:var(--suave);font-size:.72rem">{{ $ausencia->respuesta }}</div>
                            @endif
                        </td>
                        <td>
                            @if (in_array($ausencia->estado, ['SOLICITADA', 'APROBADA']) && ! $ausencia->hasta->isPast())
                                <form method="POST" action="{{ route('panel.ausencias.cancelar', $ausencia) }}"
                                      onsubmit="return confirm('¿Cancelar esta ausencia?')">
                                    @csrf
                                    <button type="submit" class="boton boton--secundario boton--pequeno">
                                        Cancelar
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/ausencias.css') }}?v=18">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=18">
<script>
(function () {
    const tipo = document.getElementById('tipo');
    const aviso = document.getElementById('avisoTipo');
    const consumen = @json(\App\Models\Ausencia::CONSUMEN_CUPO);

    function ajustar() {
        aviso.textContent = consumen.includes(tipo.value)
            ? 'Descuenta del cupo anual de vacaciones.'
            : 'No descuenta del cupo de vacaciones.';
    }

    tipo.addEventListener('change', ajustar);
    ajustar();

    // Al elegir "desde", "hasta" se rellena solo si está vacío
    const desde = document.getElementById('desde');
    const hasta = document.getElementById('hasta');

    desde.addEventListener('change', function () {
        if (!hasta.value || hasta.value < this.value) {
            hasta.value = this.value;
        }
    });
})();
</script>
@endpush

@endsection
