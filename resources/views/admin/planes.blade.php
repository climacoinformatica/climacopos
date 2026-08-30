@extends('admin.base')

@section('titulo', 'Planes')

@php $euros = fn ($n) => number_format((float) $n, 2, ',', '.') . ' €'; @endphp

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Planes de suscripción</h1>
        <p>Lo que se ofrece al dar de alta un salón y desde su panel</p>
    </div>

    <form method="POST" action="{{ route('admin.planes.sincronizar') }}">
        @csrf
        <button type="submit" class="boton">Crear precios en Stripe</button>
    </form>
</div>

{{--
    Aviso de planes sin precio en Stripe.

    Un plan sin precio no se puede contratar: al pulsar «Contratar» el
    salon recibe un error y no hay forma de que pague. Conviene verlo
    aqui antes de que lo descubra un cliente.
--}}
@php
    $sinPrecio = $productos->flatMap->planes
        ->filter(fn ($p) => $p->activo && ! $p->es_gratuito && blank($p->stripe_price_mes));
@endphp

@if ($sinPrecio->isNotEmpty())
    <p class="aviso aviso--pendiente">
        <strong>{{ $sinPrecio->count() }} plan(es) todavía no se pueden contratar</strong>
        porque no tienen precio creado en Stripe. Pulsa «Crear precios en
        Stripe» y se resuelve solo.
    </p>
@endif

@foreach ($productos as $producto)
    <div class="tarjeta">
        <h2>{{ $producto->nombre }}</h2>

        @if ($producto->planes->isEmpty())
            <p class="tarjeta__ayuda">
                Este producto no tiene ningún plan. Sin planes, nadie puede
                contratar nada.
            </p>
        @endif

        @foreach ($producto->planes as $plan)
            <form method="POST" action="{{ route('admin.planes.guardar', $plan) }}"
                  class="fila-plan">
                @csrf

                <div class="rejilla-campos">
                    <div class="campo">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required maxlength="60"
                               value="{{ $plan->nombre }}">
                    </div>

                    <div class="campo">
                        <label>Precio al mes</label>
                        <input type="number" name="precio_mes" required
                               step="0.01" min="0" value="{{ $plan->precio_mes }}">
                    </div>


                    <div class="campo">
                        <label>Soporte</label>
                        <select name="soporte" required>
                            @foreach ([
                                'NINGUNO'  => 'Sin soporte',
                                'EMAIL'    => 'Por correo',
                                'COMPLETO' => 'Teléfono, correo y remoto',
                            ] as $clave => $texto)
                                <option value="{{ $clave }}"
                                        @selected($plan->soporte === $clave)>{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="campo">
                    <label>Qué se le dice al cliente sobre el soporte</label>
                    <input type="text" name="soporte_texto" maxlength="200"
                           value="{{ $plan->soporte_texto }}"
                           placeholder="Soporte por correo, respuesta en 48 horas">
                    <p class="campo__pista">
                        Conviene concretar el compromiso. «Soporte por correo» dice
                        menos que «respuesta en 48 horas laborables», y es lo que
                        justifica pagar más.
                    </p>
                </div>

                <div class="campo">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" maxlength="200"
                           value="{{ $plan->descripcion }}">
                </div>

                <div class="fila-plan__pie">
                    <label class="casilla">
                        <input type="checkbox" name="activo" value="1" @checked($plan->activo)>
                        <span>Se ofrece a clientes nuevos</span>
                    </label>

                    <div style="display:flex;gap:.5rem;align-items:center">
                        @if ($plan->es_gratuito)
                            <span class="estado-stripe estado-stripe--na">
                                Gratuito, no pasa por Stripe
                            </span>
                        @elseif (filled($plan->stripe_price_mes))
                            <span class="estado-stripe estado-stripe--ok">Listo en Stripe</span>
                        @else
                            <span class="estado-stripe estado-stripe--falta">Sin precio en Stripe</span>
                        @endif

                        <button type="submit" class="boton boton--pequeno">Guardar</button>
                    </div>
                </div>
            </form>

            @unless ($plan->es_gratuito)
                <form method="POST" action="{{ route('admin.planes.sincronizar.uno', $plan) }}"
                      style="margin-top:-.5rem;margin-bottom:.5rem">
                    @csrf
                    <button type="submit" class="enlace-borrar" style="color:var(--suave)">
                        {{ filled($plan->stripe_price_mes)
                           ? 'Volver a crear el precio en Stripe'
                           : 'Crear el precio en Stripe' }}
                    </button>
                </form>
            @endunless

            <form method="POST" action="{{ route('admin.planes.borrar', $plan) }}"
                  onsubmit="return confirm('¿Borrar el plan {{ $plan->nombre }}?')"
                  style="text-align:right;margin-top:-.5rem;margin-bottom:1.5rem">
                @csrf
                @method('DELETE')
                <button type="submit" class="enlace-borrar">Borrar este plan</button>
            </form>
        @endforeach

        {{-- ---------- Añadir ---------- --}}
        <details class="plan-nuevo">
            <summary>Añadir un plan a {{ $producto->nombre }}</summary>

            <form method="POST" action="{{ route('admin.planes.crear') }}">
                @csrf
                <input type="hidden" name="producto_id" value="{{ $producto->id }}">

                <div class="rejilla-campos">
                    <div class="campo">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required maxlength="60">
                    </div>

                    <div class="campo">
                        <label>Precio al mes</label>
                        <input type="number" name="precio_mes" required step="0.01" min="0">
                    </div>

                    <div class="campo">
                        <label>Soporte</label>
                        <select name="soporte" required>
                            <option value="NINGUNO">Sin soporte</option>
                            <option value="EMAIL">Por correo</option>
                            <option value="COMPLETO">Teléfono, correo y remoto</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="boton boton--pequeno">Crear</button>
            </form>
        </details>
    </div>
@endforeach

<div class="tarjeta">
    <h2>Sobre estos planes</h2>

    <p class="tarjeta__ayuda">
        Los tres niveles incluyen <strong>todas las funcionalidades</strong>: se
        cobra por el soporte, no por el programa. Los límites de la tabla
        —profesionales, terminales, almacenamiento— están puestos altos y no
        se aplican.
    </p>

    <p class="tarjeta__ayuda">
        Si algún día quieres limitar algo por plan, las columnas siguen ahí y
        <code>LimitesPlan</code> las lee.
    </p>

    <h2 style="margin-top:2rem">Al cambiar un precio</h2>

    <p class="tarjeta__ayuda">
        Los precios de Stripe <strong>no se pueden editar</strong>: es una
        decisión suya, no una limitación. Un precio ya cobrado no puede
        cambiar, porque rompería el histórico de facturación de quien lo
        tenga contratado.
    </p>

    <p class="tarjeta__ayuda">
        Al cambiar el importe aquí y pulsar «Crear precios en Stripe» se crea
        uno nuevo. <strong>Los salones que ya estaban suscritos siguen pagando
        el anterior</strong> hasta que renueven o cambien de plan, que es lo
        correcto: nadie debería encontrarse una subida sin avisar.
    </p>
</div>

@push('scripts')
<style>
.fila-plan {
    border: 1px solid var(--borde);
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1rem;
}

.fila-plan__pie {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--borde);
}

.enlace-borrar {
    background: none;
    border: 0;
    color: var(--suave);
    font-size: .78rem;
    text-decoration: underline;
    cursor: pointer;
    font-family: inherit;
}
.enlace-borrar:hover { color: var(--error); }

.plan-nuevo { margin-top: 1.5rem; }
.plan-nuevo summary {
    cursor: pointer;
    color: var(--suave);
    font-size: .88rem;
    padding: .5rem 0;
}
.plan-nuevo summary:hover { color: var(--texto); }
.plan-nuevo form { margin-top: 1rem; }

.estado-stripe {
    font-size: .72rem;
    padding: .25rem .6rem;
    border-radius: 999px;
    white-space: nowrap;
}
.estado-stripe--ok {
    background: rgba(16,185,129,.15);
    color: #6ee7b7;
}
.estado-stripe--falta {
    background: rgba(245,158,11,.15);
    color: #fcd34d;
}
.estado-stripe--na {
    background: var(--panel2);
    color: var(--suave);
}
</style>
@endpush

@endsection
