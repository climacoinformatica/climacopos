@extends('portal.base')

@section('titulo', 'Tus datos')

@section('pasos')
    <a href="{{ route('portal.inicio') }}" class="paso paso--hecho">1. Servicio</a>
    <a href="{{ route('portal.hueco', ['articulo' => $articulo, 'fecha' => $fecha->toDateString()]) }}"
       class="paso paso--hecho">2. Día y hora</a>
    <span class="paso paso--activo">3. Tus datos</span>
@endsection

@section('contenido')

<div class="resumen-cita">
    <h1 class="portal-titulo">Casi está</h1>
    <dl>
        <div><dt>Servicio</dt><dd>{{ $articulo->nombre }}</dd></div>
        <div><dt>Cuándo</dt><dd>{{ $fecha->locale('es')->isoFormat('dddd D [de] MMMM') }} a las {{ $hora }}</dd></div>
        <div><dt>Con</dt><dd>{{ $profesional->alias ?: $profesional->nombre }}</dd></div>
        <div><dt>Duración</dt><dd>{{ $articulo->duracionTotal($profesional) }} minutos</dd></div>
        <div><dt>Precio</dt><dd>{{ number_format($articulo->precioPara($profesional), 2, ',', '.') }} €</dd></div>
    </dl>
</div>

<p class="reloj" id="reloj">
    Te guardamos este hueco durante <strong id="cuenta">15:00</strong>
</p>

<form method="POST" action="{{ route('portal.confirmar', $articulo) }}" class="formulario-portal">
    @csrf
    <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
    <input type="hidden" name="hora" value="{{ $hora }}">
    <input type="hidden" name="usuario_id" value="{{ $profesional->id }}">
    <input type="hidden" name="token" value="{{ $retencion->token }}">

    <div class="campo-portal">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" required autocomplete="given-name"
               value="{{ old('nombre') }}">
    </div>

    <div class="campo-portal">
        <label for="apellidos">Apellidos</label>
        <input type="text" id="apellidos" name="apellidos" autocomplete="family-name"
               value="{{ old('apellidos') }}">
    </div>

    <div class="campo-portal">
        <label for="telefono">Teléfono móvil *</label>
        <input type="tel" id="telefono" name="telefono" required autocomplete="tel"
               inputmode="tel" value="{{ old('telefono') }}">
        <small>Te avisaremos por aquí si hay cualquier cambio.</small>
    </div>

    <div class="campo-portal">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="email"
               value="{{ old('email') }}">
        <small>Opcional. Te mandamos la confirmación y el recordatorio.</small>
    </div>

    <div class="campo-portal">
        <label for="notas">¿Algo que debamos saber?</label>
        <textarea id="notas" name="notas" rows="3"
                  placeholder="Alergias, preferencias...">{{ old('notas') }}</textarea>
    </div>

    <label class="casilla-portal">
        <input type="checkbox" name="acepta_rgpd" value="1" required>
        <span>
            Acepto que {{ $empresa->nombre_comercial }} guarde mis datos para gestionar
            esta cita. Puedo pedir su borrado cuando quiera.
        </span>
    </label>

    <label class="casilla-portal">
        <input type="checkbox" name="marketing" value="1">
        <span>Quiero recibir ofertas y novedades.</span>
    </label>

    <button type="submit" class="boton-portal boton-portal--grande">Confirmar cita</button>

    <p class="letra-pequena">
        @if ((bool) config_empresa('confirmacion_automatica', false))
            Tu cita quedará confirmada al momento.
        @else
            Recibirás la confirmación en cuanto el salón la revise.
        @endif
    </p>
</form>

@push('scripts')
<script>
// Cuenta atrás de la retención del hueco
(function () {
    let restan = {{ \App\Models\ReservaTemporal::MINUTOS_VALIDEZ }} * 60;
    const marcador = document.getElementById('cuenta');
    const reloj = document.getElementById('reloj');

    const tic = setInterval(function () {
        restan--;

        if (restan <= 0) {
            clearInterval(tic);
            reloj.className = 'reloj reloj--caducado';
            reloj.innerHTML = 'El hueco ha caducado. <a href="{{ route('portal.hueco', $articulo) }}">Elige otro</a>';
            return;
        }

        const m = Math.floor(restan / 60);
        const s = restan % 60;
        marcador.textContent = m + ':' + String(s).padStart(2, '0');

        if (restan < 120) reloj.classList.add('reloj--poco');
    }, 1000);
})();
</script>
@endpush

@endsection
