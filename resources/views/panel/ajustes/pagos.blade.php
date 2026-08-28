@extends('panel.app')

@section('titulo', 'Pagos online')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Pagos online</h1>
        <p>Fianzas y prepagos de las reservas del portal</p>
    </div>
    <a href="{{ route('panel.ajustes') }}" class="boton boton--secundario">Ajustes</a>
</div>

@unless ($configurado)
    <p class="aviso aviso--error">
        La plataforma todavía no tiene configuradas las claves de Stripe.
        Hasta que se añadan al <code>.env</code> del servidor, los pagos online
        no se pueden activar.
    </p>
@endunless

{{-- ---------- Estado de la cuenta ---------- --}}
<div class="tarjeta">
    <h2>Tu cuenta de cobro</h2>

    @php
        $estados = [
            'SIN_CONECTAR' => ['Sin conectar', 'Todavía no puedes cobrar reservas por internet.', 'error'],
            'PENDIENTE'    => ['Pendiente de verificar', 'Stripe está revisando tus datos.', 'pendiente'],
            'ACTIVA'       => ['Activa', 'Puedes cobrar fianzas y prepagos.', 'ok'],
            'RESTRINGIDA'  => ['Restringida', 'Stripe necesita más documentación.', 'error'],
        ];
        [$titulo, $descripcion, $color] = $estados[$empresa->stripe_connect_estado] ?? $estados['SIN_CONECTAR'];
    @endphp

    <p class="aviso aviso--{{ $color === 'ok' ? 'ok' : ($color === 'pendiente' ? 'pendiente' : 'error') }}">
        <strong>{{ $titulo }}.</strong> {{ $descripcion }}
    </p>

    <p class="tarjeta__ayuda">
        El dinero de las reservas va <strong>directamente a tu cuenta</strong>, no a la nuestra.
        El alta se hace en Stripe porque piden documentación, titularidad e IBAN,
        y esos datos no deben pasar por aquí.
    </p>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        @if ($empresa->stripe_connect_estado === 'SIN_CONECTAR')
            <form method="POST" action="{{ route('panel.ajustes.pagos.conectar') }}">
                @csrf
                <button type="submit" class="boton" @disabled(! $configurado)>
                    Conectar mi cuenta
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.ajustes.pagos.conectar') }}">
                @csrf
                <button type="submit" class="boton boton--secundario">Completar datos en Stripe</button>
            </form>

            <form method="POST" action="{{ route('panel.ajustes.pagos.comprobar') }}">
                @csrf
                <button type="submit" class="boton boton--secundario">Comprobar estado</button>
            </form>
        @endif
    </div>

    @if ($empresa->stripe_verificado_en)
        <p class="campo__pista" style="margin-top:1rem">
            Verificada el {{ $empresa->stripe_verificado_en->format('d/m/Y H:i') }}.
        </p>
    @endif
</div>

{{-- ---------- Cómo se activa ---------- --}}
<div class="tarjeta">
    <h2>Cómo cobrar por adelantado</h2>
    <p class="tarjeta__ayuda">
        El pago se configura <strong>por servicio</strong>, no de forma global: normalmente
        interesa pedir fianza en lo que ocupa mucha agenda (mechas, alisados) y dejar
        libre lo corto.
    </p>

    <ol style="font-size:.88rem;line-height:1.9;color:var(--suave);padding-left:1.2rem">
        <li>Conecta tu cuenta aquí arriba y espera a que aparezca «Activa».</li>
        <li>Ve a <a href="{{ route('panel.catalogo.articulos') }}" class="enlace">Catálogo</a>
            y abre el servicio.</li>
        <li>En «Reserva online», elige <strong>Fianza</strong> o <strong>Pago completo</strong>.</li>
        <li>Indica el importe fijo o el porcentaje.</li>
    </ol>
</div>

{{-- ---------- Totales ---------- --}}
<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Cobrado por adelantado</span>
        <strong>{{ number_format($totales['cobrado'], 2, ',', '.') }} €</strong>
    </div>
    <div class="cifra">
        <span>Devuelto</span>
        <strong>{{ number_format($totales['devuelto'], 2, ',', '.') }} €</strong>
    </div>
    <div class="cifra">
        <span>Sin completar</span>
        <strong>{{ number_format($totales['pendiente'], 2, ',', '.') }} €</strong>
    </div>
</div>

{{-- ---------- Movimientos ---------- --}}
<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Últimos pagos</h2>
        <form method="POST" action="{{ route('panel.ajustes.pagos.sincronizar') }}">
            @csrf
            <button type="submit" class="boton boton--secundario boton--pequeno">
                Sincronizar con Stripe
            </button>
        </form>
    </div>

    @if ($pagos->isEmpty())
        <p class="campo__pista">Todavía no hay pagos online.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Referencia</th><th>Fecha</th><th>Cita</th>
                        <th>Tipo</th><th>Estado</th><th class="num">Importe</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($pagos as $pago)
                    <tr>
                        <td><small>{{ $pago->referencia }}</small></td>
                        <td>{{ $pago->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($pago->reserva)
                                <a href="{{ route('panel.agenda.cita', $pago->reserva) }}" class="enlace">
                                    {{ $pago->reserva->codigo }}
                                </a>
                                <div style="color:var(--suave);font-size:.72rem">
                                    {{ $pago->reserva->cliente_nombre }}
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $pago->tipo === 'FIANZA' ? 'Fianza' : 'Completo' }}</td>
                        <td>
                            <span class="etiqueta {{ in_array($pago->estado, ['FALLIDO','CADUCADO']) ? 'etiqueta--inactivo' : '' }}">
                                {{ $pago->etiqueta() }}
                            </span>
                            @if ($pago->devuelto_importe > 0)
                                <div style="color:var(--suave);font-size:.72rem">
                                    −{{ number_format($pago->devuelto_importe, 2, ',', '.') }} €
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ number_format($pago->importe, 2, ',', '.') }} €</td>
                        <td>
                            @if ($pago->esDevolvible())
                                <form method="POST" action="{{ route('panel.ajustes.pagos.devolver', $pago) }}"
                                      onsubmit="return confirm('¿Devolver {{ number_format($pago->pendienteDevolver(), 2, ',', '.') }} € al cliente?')">
                                    @csrf
                                    <button type="submit" class="boton boton--secundario boton--pequeno">
                                        Devolver
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
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=8">
@endpush

@endsection
