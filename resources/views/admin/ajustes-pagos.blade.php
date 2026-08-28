@extends('admin.base')

@section('titulo', 'Pagos')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Pagos de la plataforma</h1>
        <p>Claves de Stripe y comisión sobre las reservas</p>
    </div>
    @if ($tieneSecreto)
        <span class="etiqueta {{ $modo === 'PRODUCCIÓN' ? '' : 'etiqueta--inactivo' }}"
              style="font-size:.8rem;padding:.3rem .8rem">
            Modo {{ $modo }}
        </span>
    @endif
</div>

@unless ($tieneSecreto)
    <div class="guia">
        <h2>Cómo conseguir tus claves</h2>
        <ol>
            <li>
                Entra en <a href="https://dashboard.stripe.com/register" target="_blank" class="enlace">stripe.com</a>
                y crea una cuenta. Es gratis y no hay cuota fija.
            </li>
            <li>
                Dentro, ve a <strong>Desarrolladores → Claves de API</strong>.
                Verás dos: la <em>publicable</em> (empieza por <code>pk_</code>)
                y la <em>secreta</em> (empieza por <code>sk_</code>).
            </li>
            <li>
                <strong>Empieza en modo prueba.</strong> Arriba a la derecha hay un
                interruptor que pone «Modo de prueba». Con las claves
                <code>pk_test_</code> y <code>sk_test_</code> puedes probarlo todo
                sin mover dinero real.
            </li>
            <li>
                Activa <strong>Connect</strong> en el menú lateral. Es lo que permite
                que cada salón cobre en su propia cuenta.
            </li>
            <li>
                En <strong>Desarrolladores → Webhooks</strong>, añade un endpoint con la URL
                que aparece más abajo en esta página, y copia el secreto que empieza
                por <code>whsec_</code>.
            </li>
        </ol>
    </div>
@endunless

<form method="POST" action="{{ route('admin.ajustes.pagos.guardar') }}">
    @csrf

    <div class="tarjeta" style="max-width:760px">
        <h2>Claves de Stripe</h2>
        <p class="tarjeta__ayuda">
            Son las claves de <strong>tu cuenta de plataforma</strong>, no las de ningún salón.
            Cada salón conecta la suya aparte y ningún dueño de peluquería las ve.
            Se guardan cifradas: ni un volcado de la base de datos las expone.
        </p>

        <div class="campo">
            <label for="stripe_publica">Clave publicable</label>
            <input type="text" id="stripe_publica" name="stripe_publica"
                   value="{{ old('stripe_publica', $publica) }}"
                   placeholder="pk_test_..." style="font-family:monospace;font-size:.85rem">
            <p class="campo__pista">Empieza por <code>pk_test_</code> o <code>pk_live_</code>.</p>
        </div>

        <div class="campo">
            <label for="stripe_secreto">
                Clave secreta
                @if ($tieneSecreto)
                    <span class="marca-guardada">guardada</span>
                @endif
            </label>
            <input type="password" id="stripe_secreto" name="stripe_secreto"
                   placeholder="{{ $tieneSecreto ? 'Déjalo vacío para no cambiarla' : 'sk_test_...' }}"
                   style="font-family:monospace;font-size:.85rem" autocomplete="new-password">
            <p class="campo__pista">
                Empieza por <code>sk_test_</code> o <code>sk_live_</code>.
                No se muestra nunca una vez guardada.
            </p>
        </div>

        <div class="campo">
            <label for="stripe_webhook">
                Secreto del webhook
                @if ($tieneWebhook)
                    <span class="marca-guardada">guardado</span>
                @endif
            </label>
            <input type="password" id="stripe_webhook" name="stripe_webhook"
                   placeholder="{{ $tieneWebhook ? 'Déjalo vacío para no cambiarlo' : 'whsec_...' }}"
                   style="font-family:monospace;font-size:.85rem" autocomplete="new-password">
            <p class="campo__pista">
                Sirve para comprobar que los avisos de cobro vienen de Stripe de verdad
                y no de alguien que ha adivinado la URL.
            </p>
        </div>

        <div class="campo">
            <label for="comision_plataforma_pct">Tu comisión sobre cada reserva (%)</label>
            <input type="number" id="comision_plataforma_pct" name="comision_plataforma_pct"
                   step="0.01" min="0" max="50" required
                   value="{{ old('comision_plataforma_pct', $comision) }}">
            <p class="campo__pista">
                Se descuenta del pago del cliente y va a tu cuenta; el resto llega al salón.
                Déjalo en <strong>0</strong> si no quieres cobrar nada por las reservas
                y prefieres ganar solo con la cuota mensual.
            </p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button type="submit" class="boton">Guardar</button>
        </div>
    </div>
</form>

<div class="tarjeta" style="max-width:760px">
    <h2>Comprobar la conexión</h2>
    <p class="tarjeta__ayuda">
        Pregunta el saldo a Stripe para verificar que la clave funciona.
        No mueve dinero ni modifica nada.
    </p>

    <form method="POST" action="{{ route('admin.ajustes.pagos.probar') }}">
        @csrf
        <button type="submit" class="boton boton--secundario" @disabled(! $tieneSecreto)>
            Probar conexión
        </button>
    </form>
</div>

<div class="tarjeta" style="max-width:760px">
    <h2>URL del webhook</h2>
    <p class="tarjeta__ayuda">
        Cada salón tiene la suya, con su propio subdominio. En Stripe hay que dar de alta
        un endpoint con este formato, sustituyendo <code>{salon}</code>:
    </p>

    <div class="campo">
        <input type="text" readonly id="urlWebhook"
               value="https://{salon}.{{ config('climacopos.dominio_base') }}/webhook/stripe"
               style="font-family:monospace;font-size:.85rem">
    </div>

    <p class="campo__pista">
        Eventos que hay que marcar:
        <code>checkout.session.completed</code>,
        <code>checkout.session.expired</code>,
        <code>charge.refunded</code>,
        <code>account.updated</code>.
    </p>

    <button type="button" class="boton boton--secundario boton--pequeno" id="copiarUrl">
        Copiar
    </button>
</div>

@push('scripts')
<script>
document.getElementById('copiarUrl').addEventListener('click', function () {
    const campo = document.getElementById('urlWebhook');
    campo.select();
    navigator.clipboard.writeText(campo.value).then(() => {
        this.textContent = 'Copiado';
        setTimeout(() => { this.textContent = 'Copiar'; }, 2000);
    });
});
</script>
@endpush

@endsection
