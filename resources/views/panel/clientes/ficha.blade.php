@extends('panel.app')

@section('titulo', $cliente->nombreCompleto())

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $cliente->nombreCompleto() }}</h1>
        <p>
            {{ $cliente->telefono ?: 'sin teléfono' }}
            @if ($cliente->email) · {{ $cliente->email }} @endif
            · cliente desde {{ $cliente->fecha_alta?->format('m/Y') ?? '—' }}
        </p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="{{ route('panel.clientes.editar', $cliente) }}" class="boton boton--secundario">Editar</a>
        <a href="{{ route('panel.clientes.ficha.nueva', $cliente) }}" class="boton">Nueva ficha técnica</a>
    </div>
</div>

{{-- Lo que hay que saber antes de tocar a la clienta --}}
@if ($cliente->avisos_ficha)
    <p class="aviso aviso--error">
        <strong>Atención:</strong> {{ $cliente->avisos_ficha }}
    </p>
@endif

@if ($cliente->alergias)
    <p class="aviso aviso--pendiente">
        <strong>Alergias:</strong> {{ $cliente->alergias }}
    </p>
@endif

@if ($cliente->bloqueado)
    <p class="aviso aviso--error">Cliente bloqueado: no puede reservar ni asignarse a tickets.</p>
@endif

<div class="cifras">
    <div class="cifra cifra--principal">
        <span>Gastado en total</span>
        <strong>{{ number_format($gastado, 2, ',', '.') }} €</strong>
    </div>
    <div class="cifra">
        <span>Visitas</span>
        <strong>{{ $cliente->citas_totales ?? 0 }}</strong>
    </div>
    <div class="cifra">
        <span>Última visita</span>
        <strong style="font-size:1.1rem">{{ $cliente->ultima_visita?->format('d/m/Y') ?? '—' }}</strong>
    </div>
    <div class="cifra {{ $cliente->saldo_monedero > 0 ? '' : '' }}">
        <span>Monedero</span>
        <strong>{{ number_format($cliente->saldo_monedero, 2, ',', '.') }} €</strong>
    </div>
</div>

<div class="ficha-columnas">
    <div>
        {{-- ---------- Fichas técnicas ---------- --}}
        <div class="tarjeta">
            <div class="tarjeta__cabecera">
                <h2>Fichas técnicas</h2>
                <a href="{{ route('panel.clientes.ficha.nueva', $cliente) }}"
                   class="boton boton--secundario boton--pequeno">Añadir</a>
            </div>

            @if ($fichas->isEmpty())
                <p class="tarjeta__ayuda">
                    Todavía no hay fichas. Aquí se anota la fórmula exacta de cada
                    color: cuando vuelva y pida «lo mismo que la última vez»,
                    estará escrito.
                </p>
            @else
                <div class="fichas-lista">
                    @foreach ($fichas as $ficha)
                        <article class="ficha-tecnica">
                            <div class="ficha-tecnica__cabecera">
                                <div>
                                    <span class="ficha-tecnica__tipo">{{ $ficha->etiquetaTipo() }}</span>
                                    <strong>{{ $ficha->titulo ?: $ficha->etiquetaTipo() }}</strong>
                                </div>
                                <time>{{ $ficha->fecha->format('d/m/Y') }}</time>
                            </div>

                            @if ($ficha->formulaResumida())
                                <p class="ficha-tecnica__formula">{{ $ficha->formulaResumida() }}</p>
                            @endif

                            @if ($ficha->proceso)
                                <p class="ficha-tecnica__texto">{{ $ficha->proceso }}</p>
                            @endif

                            @if ($ficha->resultado)
                                <p class="ficha-tecnica__texto">
                                    <strong>Resultado:</strong> {{ $ficha->resultado }}
                                </p>
                            @endif

                            @if ($ficha->observaciones)
                                <p class="ficha-tecnica__texto ficha-tecnica__texto--aviso">
                                    {{ $ficha->observaciones }}
                                </p>
                            @endif

                            <div class="ficha-tecnica__pie">
                                <span>
                                    {{ $ficha->usuario?->nombre ?? 'sin asignar' }}
                                    @if ($ficha->valoracion)
                                        · {{ str_repeat('★', $ficha->valoracion) }}{{ str_repeat('☆', 5 - $ficha->valoracion) }}
                                    @endif
                                </span>

                                <span class="ficha-tecnica__acciones">
                                    <a href="{{ route('panel.clientes.ficha.nueva', $cliente) }}?repetir={{ $ficha->id }}">
                                        Repetir
                                    </a>
                                    <a href="{{ route('panel.clientes.ficha.editar', [$cliente, $ficha]) }}">
                                        Editar
                                    </a>
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ---------- Historial de compras ---------- --}}
        <div class="tarjeta">
            <h2>Historial de compras</h2>

            @if ($tickets->isEmpty())
                <p class="tarjeta__ayuda">Sin compras registradas.</p>
            @else
                <div class="tabla-envoltorio">
                    <table class="tabla">
                        <thead>
                            <tr><th>Documento</th><th>Fecha</th><th>Qué</th><th class="num">Total</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($tickets as $ticket)
                            <tr @class(['fila-anulada' => $ticket->estado === 'ANULADO'])>
                                <td>{{ $ticket->referencia() }}</td>
                                <td>{{ $ticket->fecha->format('d/m/Y') }}</td>
                                <td>
                                    <small>{{ $ticket->lineas->pluck('descripcion')->take(2)->join(', ') }}
                                    @if ($ticket->lineas->count() > 2)
                                        y {{ $ticket->lineas->count() - 2 }} más
                                    @endif
                                    </small>
                                </td>
                                <td class="num">{{ number_format($ticket->total, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <aside>
        {{-- ---------- Datos ---------- --}}
        <div class="tarjeta">
            <h2>Datos</h2>

            <dl class="datos-cliente">
                @if ($cliente->fecha_nac)
                    <div>
                        <dt>Cumpleaños</dt>
                        <dd>
                            {{ $cliente->fecha_nac->format('d/m') }}
                            @if ($cliente->fecha_nac->copy()->year(now()->year)->isSameDay(now()))
                                <span class="pastilla pastilla--bono">¡Hoy!</span>
                            @endif
                        </dd>
                    </div>
                @endif

                @if ($cliente->tipo_cabello)
                    <div><dt>Cabello</dt><dd>{{ $cliente->tipo_cabello }}</dd></div>
                @endif

                @if ($cliente->profesionalHabitual)
                    <div><dt>Profesional</dt><dd>{{ $cliente->profesionalHabitual->nombre }}</dd></div>
                @endif

                @if ($cliente->preferencias)
                    <div><dt>Preferencias</dt><dd>{{ $cliente->preferencias }}</dd></div>
                @endif

                @if ($cliente->notas)
                    <div><dt>Notas</dt><dd>{{ $cliente->notas }}</dd></div>
                @endif
            </dl>
        </div>

        {{-- ---------- Bonos ---------- --}}
        @if ($bonos->isNotEmpty())
            <div class="tarjeta">
                <h2>Bonos</h2>

                @foreach ($bonos as $bono)
                    <div class="bono-mini {{ $bono->estado !== 'ACTIVO' ? 'bono-mini--gastado' : '' }}">
                        <strong>{{ $bono->plantilla?->nombre }}</strong>
                        <small>{{ $bono->resumen() }}</small>
                        @if ($bono->caduca_el)
                            <small>caduca el {{ $bono->caduca_el->format('d/m/Y') }}</small>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ---------- Monedero ---------- --}}
        <div class="tarjeta">
            <h2>Monedero</h2>

            <form method="POST" action="{{ route('panel.clientes.recargar', $cliente) }}">
                @csrf
                <div class="campo">
                    <label for="importe">Importe</label>
                    <input type="number" id="importe" name="importe" step="0.01" min="0.01" required>
                </div>

                <div class="campo">
                    <label for="tipo">Concepto</label>
                    <select id="tipo" name="tipo" required>
                        <option value="RECARGA">Recarga</option>
                        <option value="REGALO">Regalo</option>
                        <option value="AJUSTE">Ajuste</option>
                    </select>
                </div>

                <button type="submit" class="boton boton--pequeno boton--ancho">Añadir saldo</button>
            </form>

            @if ($movimientos->isNotEmpty())
                <ul class="movimientos-mini">
                    @foreach ($movimientos as $movimiento)
                        <li>
                            <span>{{ $movimiento->fecha->format('d/m/Y') }} · {{ $movimiento->etiqueta() }}</span>
                            <strong @style(['color: var(--error)' => $movimiento->importe < 0])>
                                {{ number_format($movimiento->importe, 2, ',', '.') }} €
                            </strong>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ---------- Vales ---------- --}}
        @if ($vales->isNotEmpty())
            <div class="tarjeta">
                <h2>Vales</h2>
                @foreach ($vales as $vale)
                    <div class="bono-mini {{ ! $vale->estaDisponible() ? 'bono-mini--gastado' : '' }}">
                        <strong>{{ $vale->codigo }}</strong>
                        <small>
                            {{ number_format($vale->importe_restante, 2, ',', '.') }} €
                            de {{ number_format($vale->importe_inicial, 2, ',', '.') }} €
                        </small>
                    </div>
                @endforeach
            </div>
        @endif
    </aside>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}?v=16">
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=16">
@endpush

@endsection
