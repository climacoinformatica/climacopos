@extends('panel.app')

@section('titulo', 'Horarios')

@php
    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
             5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Horarios y ausencias</h1>
        <p>De aquí salen los huecos que ve el cliente en el portal</p>
    </div>
    <a href="{{ route('panel.agenda') }}" class="boton boton--secundario">Ir a la agenda</a>
</div>

@if (session('avisos'))
    @foreach (session('avisos') as $aviso)
        <p class="aviso aviso--pendiente">{{ $aviso }}</p>
    @endforeach
@endif

@if ($profesionales->isEmpty())
    <div class="vacio">
        <h3>No hay profesionales</h3>
        <p>La agenda necesita usuarios marcados como profesionales.<br>
           Créalos con <code>php artisan climacopos:crear-usuario</code> y la opción <code>--profesional</code>.</p>
    </div>
@endif

@foreach ($profesionales as $profesional)
    <div class="tarjeta">
        <h2>
            <span class="punto-color" style="background:{{ $profesional->color_agenda }}"></span>
            {{ $profesional->nombre }}
        </h2>

        <form method="POST" action="{{ route('panel.agenda.horarios.guardar', $profesional) }}">
            @csrf

            <div class="atajos">
                <span>Rellenar rápido:</span>
                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-atajo="L-V" data-m1="09:00" data-m2="14:00" data-t1="17:00" data-t2="20:00">
                    L–V partida
                </button>
                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-atajo="M-S" data-m1="09:00" data-m2="14:00" data-t1="16:00" data-t2="20:00">
                    M–S partida
                </button>
                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-atajo="L-V" data-m1="09:00" data-m2="19:00">
                    L–V continua
                </button>
                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-atajo="M-S" data-m1="10:00" data-m2="20:00">
                    M–S continua
                </button>
                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-atajo="LIMPIAR">Vaciar todo</button>
            </div>

            <div class="horario-semana">
                @foreach ($dias as $numero => $nombre)
                    @php
                        // Puede haber varias franjas el mismo día: mañana y tarde
                        $franjas = $profesional->horarios->where('dia_semana', $numero)
                                    ->sortBy('hora_ini')->values();
                        $manana = $franjas->get(0);
                        $tarde  = $franjas->get(1);
                    @endphp
                    <div class="horario-dia {{ $franjas->isNotEmpty() ? 'horario-dia--activo' : '' }}"
                         data-dia="{{ $numero }}">
                        <h4>{{ $nombre }}</h4>

                        <div class="franja">
                            <span class="franja__titulo">Mañana</span>
                            <input type="time" class="tramo0ini"
                                   name="tramos[{{ $numero }}][0][hora_ini]"
                                   value="{{ $manana ? substr($manana->hora_ini, 0, 5) : '' }}">
                            <input type="time" class="tramo0fin"
                                   name="tramos[{{ $numero }}][0][hora_fin]"
                                   value="{{ $manana ? substr($manana->hora_fin, 0, 5) : '' }}">
                        </div>

                        <div class="franja">
                            <span class="franja__titulo">Tarde</span>
                            <input type="time" class="tramo1ini"
                                   name="tramos[{{ $numero }}][1][hora_ini]"
                                   value="{{ $tarde ? substr($tarde->hora_ini, 0, 5) : '' }}">
                            <input type="time" class="tramo1fin"
                                   name="tramos[{{ $numero }}][1][hora_fin]"
                                   value="{{ $tarde ? substr($tarde->hora_fin, 0, 5) : '' }}">
                        </div>

                        <button type="button" class="horario-dia__vaciar">Día libre</button>
                    </div>
                @endforeach
            </div>

            <p class="campo__pista" style="margin:.75rem 0">
                Deja las dos franjas en blanco para marcar que ese día no trabaja.
                Para jornada continua, rellena solo «Mañana» con el horario completo.
            </p>

            <button type="submit" class="boton boton--pequeno">Guardar horario</button>
        </form>
    </div>
@endforeach

<div class="tarjeta">
    <h2>Vacaciones, bajas y festivos</h2>
    <p class="tarjeta__ayuda">
        Una excepción sin profesional afecta a todo el salón: es como se marcan los
        festivos locales y los cierres por vacaciones.
    </p>

    <form method="POST" action="{{ route('panel.agenda.excepciones.guardar') }}">
        @csrf
        <div class="rejilla-campos">
            <div class="campo">
                <label for="usuario_id">Profesional</label>
                <select id="usuario_id" name="usuario_id">
                    <option value="">— Todo el salón —</option>
                    @foreach ($profesionales as $profesional)
                        <option value="{{ $profesional->id }}">{{ $profesional->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="VACACIONES">Vacaciones</option>
                    <option value="BAJA">Baja</option>
                    <option value="FESTIVO">Festivo</option>
                    <option value="CIERRE">Cierre del salón</option>
                    <option value="HORARIO_ESPECIAL">Horario especial</option>
                </select>
            </div>

            <div class="campo">
                <label for="fecha_ini">Desde</label>
                <input type="date" id="fecha_ini" name="fecha_ini" required>
            </div>

            <div class="campo">
                <label for="fecha_fin">Hasta</label>
                <input type="date" id="fecha_fin" name="fecha_fin" required>
            </div>

            <div class="campo campoEspecial" hidden>
                <label for="hora_ini">Hora inicio</label>
                <input type="time" id="hora_ini" name="hora_ini">
            </div>

            <div class="campo campoEspecial" hidden>
                <label for="hora_fin">Hora fin</label>
                <input type="time" id="hora_fin" name="hora_fin">
            </div>

            <div class="campo">
                <label for="motivo">Motivo</label>
                <input type="text" id="motivo" name="motivo" placeholder="Día de Canarias...">
            </div>
        </div>

        <button type="submit" class="boton boton--pequeno">Añadir</button>
    </form>

    @if ($excepciones->isNotEmpty())
        <div class="tabla-envoltorio" style="margin-top:1.5rem">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Fechas</th>
                        <th>Tipo</th>
                        <th>Afecta a</th>
                        <th>Motivo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($excepciones as $excepcion)
                    <tr>
                        <td>
                            {{ $excepcion->fecha_ini->format('d/m/Y') }}
                            @if (! $excepcion->fecha_ini->isSameDay($excepcion->fecha_fin))
                                – {{ $excepcion->fecha_fin->format('d/m/Y') }}
                            @endif
                            @if ($excepcion->tipo === 'HORARIO_ESPECIAL' && $excepcion->hora_ini)
                                <div style="color:var(--suave);font-size:.72rem">
                                    {{ substr($excepcion->hora_ini, 0, 5) }}–{{ substr($excepcion->hora_fin, 0, 5) }}
                                </div>
                            @endif
                        </td>
                        <td><span class="etiqueta">{{ ucfirst(strtolower(str_replace('_', ' ', $excepcion->tipo))) }}</span></td>
                        <td>{{ $excepcion->usuario?->nombre ?? 'Todo el salón' }}</td>
                        <td>{{ $excepcion->motivo ?: '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('panel.agenda.excepciones.borrar', $excepcion) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="boton boton--secundario boton--pequeno">Quitar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@push('scripts')
<script>
// --- Atajos: rellenan la semana de golpe
document.querySelectorAll('[data-atajo]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        const formulario = this.closest('form');
        const atajo = this.dataset.atajo;

        const diasPorAtajo = {
            'L-V': [1, 2, 3, 4, 5],
            'L-S': [1, 2, 3, 4, 5, 6],
            'M-S': [2, 3, 4, 5, 6]
        };

        formulario.querySelectorAll('.horario-dia').forEach(function (celda) {
            const dia = parseInt(celda.dataset.dia);
            const campos = {
                m1: celda.querySelector('.tramo0ini'),
                m2: celda.querySelector('.tramo0fin'),
                t1: celda.querySelector('.tramo1ini'),
                t2: celda.querySelector('.tramo1fin')
            };

            const trabaja = atajo !== 'LIMPIAR' && diasPorAtajo[atajo].includes(dia);

            campos.m1.value = trabaja ? (boton.dataset.m1 || '') : '';
            campos.m2.value = trabaja ? (boton.dataset.m2 || '') : '';
            campos.t1.value = trabaja ? (boton.dataset.t1 || '') : '';
            campos.t2.value = trabaja ? (boton.dataset.t2 || '') : '';

            celda.classList.toggle('horario-dia--activo', campos.m1.value !== '');
        });
    });
});

// --- Botón "Día libre"
document.querySelectorAll('.horario-dia__vaciar').forEach(function (boton) {
    boton.addEventListener('click', function () {
        const celda = this.closest('.horario-dia');
        celda.querySelectorAll('input[type=time]').forEach(function (campo) {
            campo.value = '';
        });
        celda.classList.remove('horario-dia--activo');
    });
});

// --- Resaltar los días con horario
document.querySelectorAll('.horario-dia input[type=time]').forEach(function (campo) {
    campo.addEventListener('change', function () {
        const celda = this.closest('.horario-dia');
        const alguno = Array.from(celda.querySelectorAll('input[type=time]'))
            .some(function (c) { return c.value !== ''; });
        celda.classList.toggle('horario-dia--activo', alguno);
    });
});

// --- Las horas solo se piden en horario especial
document.getElementById('tipo').addEventListener('change', function () {
    const especial = this.value === 'HORARIO_ESPECIAL';
    document.querySelectorAll('.campoEspecial').forEach(function (campo) {
        campo.hidden = !especial;
    });
});
</script>
<style>
.atajos {
    display: flex; align-items: center; gap: .5rem;
    flex-wrap: wrap; margin-bottom: 1rem;
}
.atajos span { font-size: .78rem; color: var(--suave); }

.horario-dia { transition: border-color .12s, background .12s; }
.horario-dia--activo { border-color: var(--marca); background: rgba(99,102,241,.08); }

.franja { margin-bottom: .5rem; }
.franja__titulo {
    display: block; font-size: .62rem; color: var(--suave);
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: .15rem;
}

.horario-dia__vaciar {
    width: 100%; margin-top: .1rem; padding: .2rem;
    background: transparent; border: 1px solid transparent;
    border-radius: 6px;
    color: var(--suave); font-size: .65rem; cursor: pointer;
}
.horario-dia__vaciar:hover { color: var(--texto); border-color: var(--borde); }
</style>
@endpush

@endsection
