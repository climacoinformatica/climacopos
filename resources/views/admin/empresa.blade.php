@extends('admin.base')

@section('titulo', $empresa->nombre_comercial)

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $empresa->nombre_comercial }}</h1>
        <p>{{ $empresa->email }} · alta el {{ $empresa->created_at->format('d/m/Y') }}</p>
    </div>
    <a href="{{ route('admin.inicio') }}" class="boton boton--secundario">Volver</a>
</div>

@if (isset($datos['error']))
    <p class="aviso aviso--error">
        No se pudo leer la base de datos del salón: {{ $datos['error'] }}
    </p>
@else
    <div class="cifras">
        <div class="cifra cifra--principal">
            <span>Ventas acumuladas</span>
            <strong>{{ number_format($datos['ventas'], 2, ',', '.') }} €</strong>
        </div>
        <div class="cifra"><span>Usuarios</span><strong>{{ $datos['usuarios'] }}</strong></div>
        <div class="cifra"><span>Artículos</span><strong>{{ $datos['articulos'] }}</strong></div>
        <div class="cifra"><span>Clientes</span><strong>{{ $datos['clientes'] }}</strong></div>
        <div class="cifra"><span>Reservas</span><strong>{{ $datos['reservas'] }}</strong></div>
        <div class="cifra"><span>Tickets</span><strong>{{ $datos['tickets'] }}</strong></div>
    </div>
@endif

<div class="tarjeta" style="max-width:760px">
    <h2>Datos</h2>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <tbody>
                <tr><td>Portal</td><td><a href="{{ $empresa->urlPortal() }}" target="_blank" class="enlace">{{ $empresa->urlPortal() }} ↗</a></td></tr>
                <tr><td>Base de datos</td><td><code>{{ $empresa->tenancy_db_name }}</code></td></tr>
                <tr><td>Plan</td><td>{{ $empresa->plan?->nombre ?? '—' }}</td></tr>
                <tr><td>Estado</td><td>{{ ucfirst(strtolower($empresa->estado)) }}</td></tr>
                <tr><td>Régimen fiscal</td><td>{{ $empresa->regimen_fiscal }}</td></tr>
                <tr><td>NIF</td><td>{{ $empresa->nif ?: '—' }}</td></tr>
                <tr>
                    <td>Stripe Connect</td>
                    <td>
                        {{ $empresa->stripe_connect_id ?: 'sin conectar' }}
                        @if ($empresa->stripe_cobros_activos)
                            <span class="etiqueta" style="background:rgba(16,185,129,.18);color:#6ee7b7">cobros activos</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@if ($empresa->cuentas->isNotEmpty())
    <div class="tarjeta" style="max-width:760px">
        <h2>Cuentas propietarias</h2>
        <div class="tabla-envoltorio">
            <table class="tabla">
                <tbody>
                @foreach ($empresa->cuentas as $cuenta)
                    <tr>
                        <td>{{ $cuenta->nombre }}</td>
                        <td>{{ $cuenta->email }}</td>
                        <td>{{ $cuenta->pivot->rol }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- ---------- Gestión manual ---------- --}}
<div class="tarjeta" style="max-width:760px">
    <h2>Cambiar estado</h2>
    <p class="tarjeta__ayuda">
        Para dar cortesías, reactivar tras un pago por transferencia o ampliar una prueba.
        Queda registrado en el log de la plataforma.
    </p>

    @if ($empresa->impagos > 0)
        <p class="aviso aviso--pendiente">
            {{ $empresa->impagos }} impago(s) desde el
            {{ $empresa->primer_impago_en?->format('d/m/Y') }}.
            @if ($empresa->suspension_efectiva_en)
                Solo lectura desde el {{ $empresa->suspension_efectiva_en->format('d/m/Y H:i') }}.
            @endif
        </p>
    @endif

    @if ($empresa->borrar_a_partir_de)
        <p class="aviso aviso--error">
            Datos borrables a partir del {{ $empresa->borrar_a_partir_de->format('d/m/Y') }}.
            @if ($empresa->aviso_borrado_en)
                Avisada el {{ $empresa->aviso_borrado_en->format('d/m/Y') }}.
            @else
                <strong>Sin avisar todavía.</strong>
            @endif
        </p>
    @endif

    <form method="POST" action="{{ route('admin.empresa.estado', $empresa) }}">
        @csrf

        <div class="rejilla-campos">
            <div class="campo">
                <label for="estado">Nuevo estado</label>
                <select id="estado" name="estado" required>
                    @foreach (['PRUEBA' => 'En prueba', 'ACTIVA' => 'Activa', 'MOROSA' => 'Morosa',
                               'SUSPENDIDA' => 'Suspendida', 'CANCELADA' => 'Cancelada'] as $clave => $texto)
                        <option value="{{ $clave }}" @selected($empresa->estado === $clave)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="plan_id">Plan</label>
                <select id="plan_id" name="plan_id">
                    <option value="">— Sin cambiar —</option>
                    @foreach ($planes as $plan)
                        <option value="{{ $plan->id }}" @selected($empresa->plan_id === $plan->id)>
                            {{ $plan->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="dias">Días (prueba o cortesía)</label>
                <input type="number" id="dias" name="dias" min="1" max="365" placeholder="14">
            </div>

            <div class="campo">
                <label for="motivo">Motivo *</label>
                <input type="text" id="motivo" name="motivo" required
                       placeholder="Pago por transferencia, cortesía...">
            </div>
        </div>

        <button type="submit" class="boton boton--pequeno">Aplicar</button>
    </form>
</div>

@if ($facturas->isNotEmpty())
    <div class="tarjeta" style="max-width:760px">
        <h2>Facturas de la plataforma</h2>
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Número</th><th>Periodo</th><th>Estado</th><th class="num">Importe</th></tr>
                </thead>
                <tbody>
                @foreach ($facturas as $factura)
                    <tr>
                        <td>{{ $factura->numero ?? '—' }}</td>
                        <td>{{ $factura->periodo() }}</td>
                        <td>{{ $factura->etiqueta() }}</td>
                        <td class="num">{{ number_format($factura->importe, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
