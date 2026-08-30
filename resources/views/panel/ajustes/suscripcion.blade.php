@extends('panel.app')

@section('titulo', 'Suscripción')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Tu suscripción</h1>
        <p>Plan, forma de pago y facturas</p>
    </div>
</div>

{{-- ---------- Estado ---------- --}}
@switch($empresa->estado)
    @case('PRUEBA')
        <p class="aviso aviso--info">
            Estás en el <strong>periodo de prueba</strong>.
            @if ($diasPrueba !== null)
                Te quedan <strong>{{ $diasPrueba }} día(s)</strong>,
                hasta el {{ $empresa->prueba_hasta->format('d/m/Y') }}.
            @endif
            Elige un plan antes de que termine para no perder el acceso.
        </p>
        @break

    @case('MOROSA')
        <p class="aviso aviso--pendiente">
            <strong>No hemos podido cobrar tu cuota.</strong>
            Todo sigue funcionando con normalidad, pero si el segundo intento
            también falla, tu cuenta pasará a solo lectura.
            Revisa tu tarjeta abajo.
        </p>
        @break

    @case('SUSPENDIDA')
        @if ($soloLectura)
            <p class="aviso aviso--error">
                <strong>Tu cuenta está en modo solo lectura.</strong>
                Puedes consultar tu agenda y tus clientes para atender a quien ya
                tenía cita, pero no puedes vender, crear reservas ni sacar informes.
                Se reactiva en cuanto se cobre la cuota.
            </p>
        @else
            <p class="aviso aviso--pendiente">
                <strong>Tu cuenta pasará a solo lectura esta noche.</strong>
                No cortamos el servicio en mitad de la jornada: tienes hasta las
                {{ $empresa->suspension_efectiva_en?->format('H:i') }} de
                {{ $empresa->suspension_efectiva_en?->locale('es')->isoFormat('dddd') }}
                para regularizar el pago.
            </p>
        @endif

        @if ($empresa->borrar_a_partir_de)
            <p class="aviso aviso--error">
                Conservamos tus datos hasta el
                <strong>{{ $empresa->borrar_a_partir_de->format('d/m/Y') }}</strong>.
                Pasada esa fecha se eliminan de forma definitiva.
            </p>
        @endif
        @break

    @case('CANCELADA')
        <p class="aviso aviso--error">
            Tu suscripción está cancelada. Puedes volver a contratar cuando quieras;
            tus datos se conservan hasta el
            {{ $empresa->borrar_a_partir_de?->format('d/m/Y') ?? '—' }}.
        </p>
        @break

    @default
        <p class="aviso aviso--ok">
            Suscripción activa.
            @if ($empresa->suscripcion_hasta)
                Próxima renovación el {{ $empresa->suscripcion_hasta->format('d/m/Y') }}.
            @endif
            @if ($empresa->cancela_al_terminar)
                <strong>Has pedido cancelar: no se renovará.</strong>
            @endif
        </p>
@endswitch

{{-- ---------- Plan actual ---------- --}}
@if ($plan)
    <div class="tarjeta">
        <h2>Tu plan: {{ $plan->nombre }}</h2>

        @php
            $usadas = \App\Support\LimitesPlan::facturasDelMes();
            $tope   = (int) ($plan->max_facturas_mes ?? 0);
            $pct    = $tope > 0 ? min(100, round(($usadas / $tope) * 100)) : 0;
        @endphp

        <div class="arqueo">
            <div class="arqueo__dato arqueo__dato--destacado">
                <span>Cuota</span>
                <strong>
                    @if ($plan->es_gratuito || $plan->precio_mes <= 0)
                        Gratis
                    @else
                        {{ number_format($plan->precio_mes, 2, ',', '.') }} €
                    @endif
                </strong>
                <span>{{ $plan->es_gratuito ? 'sin coste' : 'al mes' }}</span>
            </div>

            <div class="arqueo__dato">
                <span>Facturas este mes</span>
                <strong>
                    {{ $usadas }}@if ($tope > 0) <small style="font-weight:400">de {{ $tope }}</small>@endif
                </strong>
                <span>{{ $tope > 0 ? 'límite del plan' : 'sin límite' }}</span>
            </div>

            <div class="arqueo__dato">
                <span>Profesionales</span>
                <strong>
                    {{ $plan->max_profesionales > 0 && $plan->max_profesionales < 100
                       ? $plan->max_profesionales : 'Sin límite' }}
                </strong>
            </div>

            <div class="arqueo__dato">
                <span>Soporte</span>
                <strong style="font-size:1rem">{{ $plan->soporteLegible() }}</strong>
            </div>
        </div>

        {{--
            Barra de consumo, solo cuando hay limite.

            En el plan gratuito importa mucho: el salon tiene que ver que
            se acerca al tope ANTES de que el panel se le bloquee un lunes
            por la mañana.
        --}}
        @if ($tope > 0)
            <div class="consumo">
                <div class="consumo__barra">
                    <div @class([
                            'consumo__relleno',
                            'consumo__relleno--aviso' => $pct >= 80 && $pct < 95,
                            'consumo__relleno--grave' => $pct >= 95,
                         ])
                         style="width: {{ $pct }}%"></div>
                </div>

                <p class="consumo__texto">
                    @if ($usadas >= $tope)
                        Has llegado al límite de este mes. <strong>Puedes seguir
                        cobrando con normalidad</strong>, pero el resto del programa
                        está limitado hasta que amplíes el plan o empiece el mes
                        que viene.
                    @elseif ($pct >= 80)
                        Te quedan <strong>{{ $tope - $usadas }} facturas</strong> este mes.
                    @else
                        Te quedan {{ $tope - $usadas }} facturas este mes.
                    @endif
                </p>
            </div>
        @endif

        @if ($empresa->stripe_customer_id)
            <form method="POST" action="{{ route('panel.suscripcion.portal') }}" style="margin-top:1.25rem">
                @csrf
                <button type="submit" class="boton">Cambiar tarjeta, plan o cancelar</button>
            </form>
            <p class="campo__pista" style="margin-top:.5rem">
                Se abre el portal seguro de Stripe. No guardamos los datos de tu tarjeta.
            </p>
        @endif
    </div>
@endif

{{-- ---------- Planes ---------- --}}
@if (! $plan || in_array($empresa->estado, ['PRUEBA', 'SUSPENDIDA', 'CANCELADA', 'MOROSA']))
    <div class="tarjeta">
        <h2>{{ $plan ? 'Cambiar de plan' : 'Elige tu plan' }}</h2>

        <div class="planes">
            @foreach ($planes as $opcion)
                <div class="plan {{ $plan?->id === $opcion->id ? 'plan--actual' : '' }}">
                    @if ($plan?->id === $opcion->id)
                        <span class="plan__insignia">Tu plan</span>
                    @endif

                    <h3>{{ $opcion->nombre }}</h3>

                    <p class="plan__precio">
                        @if ($opcion->es_gratuito || $opcion->precio_mes <= 0)
                            Gratis
                        @else
                            {{ number_format($opcion->precio_mes, 2, ',', '.') }} €
                            <small>/mes</small>
                        @endif
                    </p>

                    {{--
                        Los limites SOLO se enseñan cuando los hay.

                        En los planes de pago va todo sin limite, asi que
                        listar «999 profesionales» no dice nada. En el
                        gratuito si importan: son la razon de que exista un
                        plan de pago.
                    --}}
                    <ul class="plan__lista">
                        @if ($opcion->max_facturas_mes > 0)
                            <li class="plan__limite">
                                Hasta <strong>{{ $opcion->max_facturas_mes }} facturas</strong> al mes
                            </li>
                        @else
                            <li><strong>Facturas sin límite</strong></li>
                        @endif

                        @if ($opcion->max_profesionales > 0 && $opcion->max_profesionales < 100)
                            <li class="plan__limite">
                                {{ $opcion->max_profesionales }}
                                {{ $opcion->max_profesionales === 1 ? 'profesional' : 'profesionales' }}
                            </li>
                        @else
                            <li>Profesionales sin límite</li>
                        @endif

                        <li>Todas las funciones del programa</li>
                        <li>Reservas online y cobro con tarjeta</li>
                        <li>VERI*FACTU incluido</li>
                    </ul>

                    <p class="plan__soporte plan__soporte--{{ strtolower($opcion->soporte ?? 'ninguno') }}">
                        {{ $opcion->soporteLegible() }}
                    </p>

                    <form method="POST" action="{{ route('panel.suscripcion.contratar') }}">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $opcion->id }}">

                        {{--
                            Solo pago mensual.

                            El ciclo viaja oculto: el controlador lo sigue
                            esperando, pero no hay nada que elegir. Un
                            desplegable con una sola opcion es una decision
                            que no existe.
                        --}}
                        <input type="hidden" name="ciclo" value="MENSUAL">

                        <button type="submit" class="boton boton--ancho">
                            @if ($plan?->id === $opcion->id)
                                Tu plan actual
                            @elseif ($opcion->es_gratuito || $opcion->precio_mes <= 0)
                                Empezar gratis
                            @else
                                Contratar
                            @endif
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- ---------- Facturas ---------- --}}
<div class="tarjeta">
    <h2>Tus facturas</h2>

    @if ($facturas->isEmpty())
        <p class="campo__pista">Todavía no hay facturas.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Número</th><th>Periodo</th><th>Estado</th>
                        <th class="num">Importe</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($facturas as $factura)
                    <tr>
                        <td>{{ $factura->numero ?? '—' }}</td>
                        <td>{{ $factura->periodo() }}</td>
                        <td>
                            <span class="etiqueta {{ $factura->estado === 'IMPAGADA' ? 'etiqueta--inactivo' : '' }}">
                                {{ $factura->etiqueta() }}
                            </span>
                        </td>
                        <td class="num">{{ number_format($factura->importe, 2, ',', '.') }} €</td>
                        <td>
                            @if ($factura->url_factura)
                                <a href="{{ $factura->url_factura }}" target="_blank"
                                   class="boton boton--secundario boton--pequeno">Ver</a>
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
<style>
/* ---------- Barra de consumo ---------- */
.consumo { margin-top: 1.5rem; }

.consumo__barra {
    height: 8px;
    background: var(--panel2);
    border-radius: 999px;
    overflow: hidden;
}

.consumo__relleno {
    height: 100%;
    background: var(--ok, #10b981);
    border-radius: 999px;
    transition: width .3s;
}
.consumo__relleno--aviso { background: #f59e0b; }
.consumo__relleno--grave { background: #ef4444; }

.consumo__texto {
    margin-top: .6rem;
    font-size: .85rem;
    color: var(--suave);
    line-height: 1.55;
}

/* ---------- Soporte del plan ---------- */
.plan__soporte {
    margin: 1rem 0;
    padding: .7rem .85rem;
    border-radius: 8px;
    font-size: .86rem;
    font-weight: 600;
    text-align: center;
    line-height: 1.45;
}
.plan__soporte--ninguno {
    background: var(--panel2);
    color: var(--suave);
    font-weight: 500;
}
.plan__soporte--email {
    background: rgba(99,102,241,.12);
    border: 1px solid rgba(99,102,241,.3);
    color: #a5b4fc;
}
.plan__soporte--completo {
    background: rgba(16,185,129,.12);
    border: 1px solid rgba(16,185,129,.3);
    color: #6ee7b7;
}

/* Los limites del gratuito, destacados: son la razon de pagar */
.plan__limite { color: #fcd34d; }
</style>
<link rel="stylesheet" href="{{ asset('css/planes.css') }}?v=9">
@endpush

@endsection
