@extends('admin.base')

@section('titulo', 'Empresas')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Empresas</h1>
        <p>{{ $totales['total'] }} salones dados de alta</p>
    </div>
</div>

<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Activas</span>
        <strong>{{ $totales['activas'] }}</strong>
    </div>
    <div class="cifra">
        <span>En prueba</span>
        <strong>{{ $totales['prueba'] }}</strong>
    </div>
    <div class="cifra {{ $totales['suspendidas'] > 0 ? 'cifra--alerta' : '' }}">
        <span>Morosas o suspendidas</span>
        <strong>{{ $totales['suspendidas'] }}</strong>
    </div>
    <div class="cifra">
        <span>Cobran online</span>
        <strong>{{ $totales['con_stripe'] }}</strong>
    </div>
</div>

<div class="tarjeta" style="padding:.5rem">
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Salón</th><th>Portal</th><th>Plan</th>
                    <th>Estado</th><th>Pagos online</th><th>Alta</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($empresas as $empresa)
                <tr>
                    <td>
                        <strong>{{ $empresa->nombre_comercial }}</strong>
                        <div style="color:var(--suave);font-size:.72rem">
                            {{ $empresa->email }} · BD {{ $empresa->tenancy_db_name }}
                        </div>
                    </td>
                    <td>
                        <a href="{{ $empresa->urlPortal() }}" target="_blank" class="enlace">
                            {{ $empresa->slug }} ↗
                        </a>
                    </td>
                    <td>{{ $empresa->plan?->nombre ?? '—' }}</td>
                    <td>
                        <span class="etiqueta {{ in_array($empresa->estado, ['SUSPENDIDA','CANCELADA','MOROSA']) ? 'etiqueta--inactivo' : '' }}">
                            {{ ucfirst(strtolower($empresa->estado)) }}
                        </span>
                        @if ($empresa->estado === 'PRUEBA' && $empresa->prueba_hasta)
                            <div style="color:var(--suave);font-size:.7rem">
                                hasta {{ $empresa->prueba_hasta->format('d/m/Y') }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if ($empresa->stripe_cobros_activos)
                            <span class="etiqueta" style="background:rgba(16,185,129,.18);color:#6ee7b7">Sí</span>
                        @else
                            <span style="color:var(--suave)">—</span>
                        @endif
                    </td>
                    <td>{{ $empresa->created_at->format('d/m/Y') }}</td>
                    <td>
                        <a href="{{ route('admin.empresa', $empresa) }}"
                           class="boton boton--secundario boton--pequeno">Ver</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--suave)">
                    Todavía no hay ninguna empresa.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($planes->isNotEmpty())
    <div class="tarjeta">
        <h2>Planes</h2>
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr><th>Plan</th><th class="num">Precio/mes</th>
                        <th class="num">Profesionales</th><th class="num">Empresas</th></tr>
                </thead>
                <tbody>
                @foreach ($planes as $plan)
                    <tr>
                        <td>{{ $plan->nombre }}</td>
                        <td class="num">{{ number_format($plan->precio_mes, 2, ',', '.') }} €</td>
                        <td class="num">{{ $plan->max_profesionales }}</td>
                        <td class="num">{{ $plan->empresas_count }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
