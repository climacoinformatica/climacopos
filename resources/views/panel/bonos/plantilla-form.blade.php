@extends('panel.app')

@section('titulo', $plantilla->exists ? 'Editar bono' : 'Nuevo bono')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $plantilla->exists ? 'Editar bono' : 'Nuevo bono' }}</h1>
        <p>Un pack que la clienta paga por adelantado</p>
    </div>
    <a href="{{ route('panel.bonos.plantillas') }}" class="boton boton--secundario">Volver</a>
</div>

<form method="POST"
      action="{{ $plantilla->exists
                 ? route('panel.bonos.guardar.editar', $plantilla)
                 : route('panel.bonos.guardar') }}">
    @csrf

    <div class="tarjeta" style="max-width:720px">
        <h2>Qué se vende</h2>

        <div class="campo">
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="120"
                   value="{{ old('nombre', $plantilla->nombre) }}"
                   placeholder="Bono 5 manicuras">
            <p class="campo__pista">Es lo que verá la clienta en el ticket.</p>
        </div>

        <div class="campo">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion">{{ old('descripcion', $plantilla->descripcion) }}</textarea>
        </div>

        <div class="campo">
            <label for="modalidad">Modalidad *</label>
            <select id="modalidad" name="modalidad" required>
                <option value="SESIONES" @selected(old('modalidad', $plantilla->modalidad) === 'SESIONES')>
                    Por sesiones — «5 manicuras»
                </option>
                <option value="SALDO" @selected(old('modalidad', $plantilla->modalidad) === 'SALDO')>
                    Por saldo — «recarga 100 y te damos 120»
                </option>
            </select>
            <p class="campo__pista" id="ayudaModalidad"></p>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="precio">Precio de venta *</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0.01" required
                       value="{{ old('precio', $plantilla->precio) }}">
            </div>

            <div class="campo">
                <label for="impuesto_pct">Impuesto (%)</label>
                <input type="number" id="impuesto_pct" name="impuesto_pct" step="0.01" min="0" max="100"
                       value="{{ old('impuesto_pct', $plantilla->impuesto_pct ?? 0) }}">
            </div>
        </div>
    </div>

    {{-- Sesiones --}}
    <div class="tarjeta" style="max-width:720px" id="bloqueSesiones">
        <h2>Sesiones</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="num_sesiones">Cuántas sesiones incluye</label>
                <input type="number" id="num_sesiones" name="num_sesiones" min="1" max="999"
                       value="{{ old('num_sesiones', $plantilla->num_sesiones) }}">
            </div>

            <div class="campo">
                <label for="articulo_id">Servicio concreto</label>
                <select id="articulo_id" name="articulo_id">
                    <option value="">— Cualquiera de una familia —</option>
                    @foreach ($articulos as $articulo)
                        <option value="{{ $articulo->id }}"
                                @selected(old('articulo_id', $plantilla->articulo_id) == $articulo->id)>
                            {{ $articulo->nombre }} · {{ number_format($articulo->precio, 2, ',', '.') }} €
                        </option>
                    @endforeach
                </select>
                <p class="campo__pista">
                    Si eliges un servicio, el bono solo vale para ese.
                </p>
            </div>
        </div>
    </div>

    {{-- Saldo --}}
    <div class="tarjeta" style="max-width:720px" id="bloqueSaldo">
        <h2>Saldo</h2>

        <div class="campo">
            <label for="saldo_otorgado">Saldo que recibe la clienta</label>
            <input type="number" id="saldo_otorgado" name="saldo_otorgado" step="0.01" min="0"
                   value="{{ old('saldo_otorgado', $plantilla->saldo_otorgado) }}">
            <p class="campo__pista">
                Suele ser mayor que el precio: ahí está el incentivo para
                que pague por adelantado.
            </p>
        </div>
    </div>

    <div class="tarjeta" style="max-width:720px">
        <h2>Condiciones</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="familia_id">Limitar a una familia</label>
                <select id="familia_id" name="familia_id">
                    <option value="">— Sin límite —</option>
                    @foreach ($familias as $familia)
                        <option value="{{ $familia->id }}"
                                @selected(old('familia_id', $plantilla->familia_id) == $familia->id)>
                            {{ $familia->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="caducidad_meses">Caduca a los (meses)</label>
                <input type="number" id="caducidad_meses" name="caducidad_meses" min="1" max="120"
                       value="{{ old('caducidad_meses', $plantilla->caducidad_meses) }}"
                       placeholder="Vacío = sin caducidad">
                <p class="campo__pista">
                    Doce meses es lo habitual. Sin caducidad es más generoso,
                    pero deja saldo pendiente indefinidamente en tu contabilidad.
                </p>
            </div>

            <div class="campo">
                <label for="color">Color en el TPV</label>
                <input type="color" id="color" name="color"
                       value="{{ old('color', $plantilla->color ?? '#8b5cf6') }}">
            </div>

            <div class="campo">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="0"
                       value="{{ old('orden', $plantilla->orden ?? 0) }}">
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="activo" name="activo" value="1"
                   @checked(old('activo', $plantilla->activo ?? true))>
            <div>
                <label for="activo">Se vende</label>
                <small>Desactívalo para dejar de venderlo sin afectar a los ya vendidos.</small>
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="vender_online" name="vender_online" value="1"
                   @checked(old('vender_online', $plantilla->vender_online ?? false))>
            <div>
                <label for="vender_online">Vender desde el portal</label>
                <small>Todavía no implementado; se prepara para más adelante.</small>
            </div>
        </div>
    </div>

    @unless ($plantilla->exists)
        <p class="aviso aviso--info" style="max-width:720px">
            Al guardar se crea también el artículo que lo vende, en una familia
            llamada «Bonos», para que puedas cobrarlo desde el TPV sin más pasos.
        </p>
    @endunless

    <button type="submit" class="boton">
        {{ $plantilla->exists ? 'Guardar cambios' : 'Crear bono' }}
    </button>
</form>

@push('scripts')
<script>
(function () {
    const modalidad = document.getElementById('modalidad');
    const sesiones  = document.getElementById('bloqueSesiones');
    const saldo     = document.getElementById('bloqueSaldo');
    const ayuda     = document.getElementById('ayudaModalidad');

    const textos = {
        SESIONES: 'Se descuenta una sesión por uso, sin mirar el precio del día. ' +
                  'Si subes tarifas, quien compró el bono no se ve afectada.',
        SALDO:    'Se descuenta el importe real de lo consumido. Sirve para cualquier servicio.'
    };

    function ajustar() {
        const esSesiones = modalidad.value === 'SESIONES';

        sesiones.hidden = !esSesiones;
        saldo.hidden = esSesiones;
        ayuda.textContent = textos[modalidad.value] || '';
    }

    modalidad.addEventListener('change', ajustar);
    ajustar();
})();
</script>
<link rel="stylesheet" href="{{ asset('css/bonos.css') }}?v=14">
@endpush

@endsection
