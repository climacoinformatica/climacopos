@extends('panel.app')

@section('titulo', 'Ajustes de reservas')

@php
    $v = fn ($clave, $porDefecto = '') => old($clave, $ajustes[$clave] ?? $porDefecto);
@endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Ajustes de reservas</h1>
        <p>Condicionan lo que el cliente ve en el portal</p>
    </div>
    <a href="{{ route('portal.inicio') }}" target="_blank" class="boton boton--secundario">
        Ver el portal ↗
    </a>
</div>

<form method="POST" action="{{ route('panel.ajustes.guardar') }}">
    @csrf

    <div class="tarjeta" style="max-width:760px">
        <h2>Confirmación</h2>

        <div class="casilla">
            <input type="checkbox" id="confirmacion_automatica" name="confirmacion_automatica" value="1"
                   @checked($v('confirmacion_automatica') === 'true')>
            <div>
                <label for="confirmacion_automatica">Confirmar automáticamente las reservas online</label>
                <small>
                    Sin marcar, cada reserva queda pendiente y hace destellar el aviso hasta que
                    alguien la acepta. El hueco se retiene mientras tanto, así que nadie más
                    puede cogerlo. Es lo recomendable al empezar: se ve qué reservas llegan
                    antes de fiarse del automático.
                </small>
            </div>
        </div>

        <div class="campo">
            <label for="caducidad_pendiente_horas">Caducidad de las pendientes (horas)</label>
            <input type="number" id="caducidad_pendiente_horas" name="caducidad_pendiente_horas"
                   min="1" max="720" required value="{{ $v('caducidad_pendiente_horas', 48) }}">
            <p class="campo__pista">
                Si nadie decide en este plazo, la reserva se rechaza sola y el hueco se libera.
            </p>
        </div>
    </div>

    <div class="tarjeta" style="max-width:760px">
        <h2>Plazos</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="antelacion_min_horas">Antelación mínima (horas)</label>
                <input type="number" id="antelacion_min_horas" name="antelacion_min_horas"
                       min="0" max="720" required value="{{ $v('antelacion_min_horas', 2) }}">
                <p class="campo__pista">
                    Con 2, nadie puede reservar para dentro de una hora. Desde el panel
                    no se aplica: el salón puede meter una cita para ya mismo.
                </p>
            </div>

            <div class="campo">
                <label for="antelacion_max_dias">Antelación máxima (días)</label>
                <input type="number" id="antelacion_max_dias" name="antelacion_max_dias"
                       min="1" max="365" required value="{{ $v('antelacion_max_dias', 60) }}">
                <p class="campo__pista">Hasta cuándo puede reservar el cliente.</p>
            </div>

            <div class="campo">
                <label for="cancelacion_horas_min">Cancelar hasta (horas antes)</label>
                <input type="number" id="cancelacion_horas_min" name="cancelacion_horas_min"
                       min="0" max="720" required value="{{ $v('cancelacion_horas_min', 24) }}">
                <p class="campo__pista">
                    Pasado ese plazo, el cliente ya no puede cancelar por internet
                    y se le pide que llame.
                </p>
            </div>

            <div class="campo">
                <label for="no_shows_para_exigir_pago">Plantones para exigir pago</label>
                <input type="number" id="no_shows_para_exigir_pago" name="no_shows_para_exigir_pago"
                       min="1" max="20" required value="{{ $v('no_shows_para_exigir_pago', 2) }}">
                <p class="campo__pista">
                    A partir de este número de plantones, ese cliente tendrá que pagar
                    por adelantado. Se activará con la pasarela, en la Fase 8.
                </p>
            </div>
        </div>
    </div>

    <div class="tarjeta" style="max-width:760px">
        <h2>Vista de la agenda</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="agenda_hora_ini">La agenda empieza a las</label>
                <input type="time" id="agenda_hora_ini" name="agenda_hora_ini" required
                       value="{{ $v('agenda_hora_ini', '08:00') }}">
            </div>

            <div class="campo">
                <label for="agenda_hora_fin">y termina a las</label>
                <input type="time" id="agenda_hora_fin" name="agenda_hora_fin" required
                       value="{{ $v('agenda_hora_fin', '21:00') }}">
            </div>
        </div>

        <p class="campo__pista">
            Solo afecta a lo que se dibuja en pantalla. Quien decide los huecos
            reservables es el horario de cada profesional.
        </p>
    </div>

    <button type="submit" class="boton">Guardar ajustes</button>
</form>

<div class="tarjeta" style="max-width:760px;margin-top:2rem">
    <h2>Tu enlace de reservas</h2>
    <p class="tarjeta__ayuda">
        Este es el enlace que se pone en la biografía de Instagram, en Google
        y en WhatsApp. Es por donde entrarán las reservas.
    </p>

    <div class="campo">
        <input type="text" readonly id="enlacePortal"
               value="{{ tenant()->urlPortal() }}"
               style="font-family:monospace;font-size:.9rem">
    </div>

    <button type="button" class="boton boton--secundario boton--pequeno" id="copiarEnlace">
        Copiar enlace
    </button>
</div>

@push('scripts')
<script>
document.getElementById('copiarEnlace').addEventListener('click', function () {
    const campo = document.getElementById('enlacePortal');
    campo.select();
    navigator.clipboard.writeText(campo.value).then(() => {
        this.textContent = 'Copiado';
        setTimeout(() => { this.textContent = 'Copiar enlace'; }, 2000);
    });
});
</script>
@endpush

@endsection
