@extends('web.base')

@section('titulo', 'Crear mi salón')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <h1>Crea tu salón</h1>
        <p class="subtitulo">
            En un minuto tendrás tu propia dirección y podrás empezar a
            trabajar. El primer mes es de prueba.
        </p>

        @if ($errors->any())
            <div class="mensaje mensaje--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.alta.crear') }}" class="formulario">
            @csrf

            <div class="campo">
                <label for="nombre_comercial">Nombre del salón *</label>
                <input type="text" id="nombre_comercial" name="nombre_comercial" required
                       maxlength="120" value="{{ old('nombre_comercial', $cuenta->empresa) }}"
                       placeholder="Peluquería Marta">
                <small>Es el que verán tus clientas en el ticket y en las reservas.</small>
            </div>

            <div class="campo">
                <label for="slug">Tu dirección *</label>

                <div class="campo-dominio">
                    <input type="text" id="slug" name="slug" required maxlength="40"
                           value="{{ old('slug', $propuesta) }}"
                           pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?"
                           autocomplete="off" spellcheck="false">
                    <span>.climacopos.com</span>
                </div>

                <p class="estado-slug" id="estadoSlug"></p>

                <small>
                    Es la dirección donde entrarás tú y donde reservarán tus
                    clientas. <strong>No se puede cambiar después</strong>, así
                    que elígela con calma.
                </small>
            </div>

            @if ($planes->isNotEmpty())
                <div class="campo">
                    <label for="plan_id">Plan</label>
                    <select id="plan_id" name="plan_id">
                        @foreach ($planes as $plan)
                            <option value="{{ $plan->id }}">
                                {{ $plan->nombre }}
                                @if ($plan->precio_mes > 0)
                                    · {{ number_format($plan->precio_mes, 2, ',', '.') }} €/mes
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <small>Puedes cambiarlo cuando quieras. No se cobra nada durante la prueba.</small>
                </div>
            @endif

            <label class="casilla">
                <input type="checkbox" name="acepta" value="1" required>
                <span>
                    Acepto las
                    <a href="{{ route('web.legal', 'condiciones') }}" target="_blank">condiciones del servicio</a>. *
                </span>
            </label>

            <button type="submit" class="boton boton--grande boton--marca boton--ancho" id="botonCrear">
                Crear mi salón
            </button>
        </form>

        <p class="pie-formulario">
            <a href="{{ route('web.area') }}">Volver a mi cuenta</a>
        </p>
    </div>
</section>

@push('scripts')
<script>
(function () {
    const campo  = document.getElementById('slug');
    const estado = document.getElementById('estadoSlug');
    const boton  = document.getElementById('botonCrear');

    let espera = null;

    // Se normaliza mientras escribe: nadie tiene por qué saber las
    // reglas de los nombres de dominio
    campo.addEventListener('input', function () {
        this.value = this.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9-]/g, '-')
            .replace(/-+/g, '-');

        clearTimeout(espera);
        estado.textContent = '';
        estado.className = 'estado-slug';

        if (this.value.length < 3) return;

        espera = setTimeout(() => comprobar(this.value), 400);
    });

    async function comprobar(slug) {
        estado.textContent = 'Comprobando…';

        try {
            const respuesta = await fetch(
                '{{ route('web.alta.comprobar') }}?slug=' + encodeURIComponent(slug)
            );

            const datos = await respuesta.json();

            if (datos.ok) {
                estado.textContent = '✓ Está libre';
                estado.className = 'estado-slug estado-slug--ok';
                boton.disabled = false;
            } else {
                estado.textContent = '✕ ' + datos.motivo
                    + (datos.sugerencia ? ' Prueba con «' + datos.sugerencia + '».' : '');
                estado.className = 'estado-slug estado-slug--error';
                boton.disabled = true;
            }
        } catch (e) {
            estado.textContent = '';
        }
    }

    if (campo.value.length >= 3) comprobar(campo.value);
})();
</script>
@endpush

@endsection
