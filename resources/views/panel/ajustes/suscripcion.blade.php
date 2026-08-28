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

        <div class="arqueo">
            <div class="arqueo__dato arqueo__dato--destacado">
                <span>Cuota</span>
                <strong>
                    {{ number_format($empresa->ciclo === 'ANUAL' ? $plan->precio_ano : $plan->precio_mes, 2, ',', '.') }} €
                </strong>
                <span>{{ $empresa->ciclo === 'ANUAL' ? 'al año' : 'al mes' }}</span>
            </div>
            <div class="arqueo__dato">
                <span>Profesionales</span>
                <strong>{{ $plan->max_profesionales }}</strong>
            </div>
            <div class="arqueo__dato">
                <span>Terminales</span>
                <strong>{{ $plan->max_terminales }}</strong>
            </div>
        </div>

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
                        {{ number_format($opcion->precio_mes, 2, ',', '.') }} €
                        <small>/mes</small>
                    </p>

                    @if ($opcion->precio_ano > 0)
                        <p class="plan__anual">
                            o {{ number_format($opcion->precio_ano, 2, ',', '.') }} € al año
                            @php $ahorro = ($opcion->precio_mes * 12) - $opcion->precio_ano; @endphp
                            @if ($ahorro > 0)
                                <em>(ahorras {{ number_format($ahorro, 2, ',', '.') }} €)</em>
                            @endif
                        </p>
                    @endif

                    <ul class="plan__lista">
                        <li>{{ $opcion->max_profesionales }} profesional(es)</li>
                        <li>{{ $opcion->max_terminales }} terminal(es)</li>
                        <li>{{ $opcion->reservas_online ? 'Reservas online' : 'Sin reservas online' }}</li>
                        <li>{{ $opcion->pagos_online ? 'Cobro de fianzas' : 'Sin cobro online' }}</li>
                        <li>{{ $opcion->verifactu ? 'VERI*FACTU' : 'Sin VERI*FACTU' }}</li>
                        @if ($opcion->dominio_propio)
                            <li>Dominio propio</li>
                        @endif
                    </ul>

                    <form method="POST" action="{{ route('panel.suscripcion.contratar') }}">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $opcion->id }}">

                        <div class="campo">
                            <select name="ciclo">
                                <option value="MENSUAL">Pago mensual</option>
                                @if ($opcion->precio_ano > 0)
                                    <option value="ANUAL">Pago anual</option>
                                @endif
                            </select>
                        </div>

                        <button type="submit" class="boton boton--ancho">
                            {{ $plan?->id === $opcion->id ? 'Renovar' : 'Contratar' }}
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
<link rel="stylesheet" href="{{ asset('css/planes.css') }}?v=9">
@endpush

@endsection
