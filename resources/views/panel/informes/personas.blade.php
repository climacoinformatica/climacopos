<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Ventas y comisiones por profesional</h2>

        {{--
            Las tres salidas llevan los MISMOS filtros que hay en
            pantalla: request()->query() arrastra fechas y profesional,
            asi que lo exportado es exactamente lo que se esta viendo.
        --}}
        <div class="acciones-fila">
            <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'profesionales']) }}"
               class="boton boton--secundario boton--pequeno">CSV</a>

            <a href="{{ route('panel.documentos.profesionales.pdf', request()->query()) }}"
               class="boton boton--secundario boton--pequeno">PDF</a>

            <button type="button" class="boton boton--secundario boton--pequeno"
                    onclick="document.getElementById('modalProfesionales').hidden = false">
                Correo
            </button>
        </div>
    </div>

    {{-- Filtro por profesional. A quien solo ve lo suyo no se le ofrece --}}
    @if (! ($soloPropios ?? false) && ($profesionales ?? collect())->isNotEmpty())
        <form method="GET" class="filtros" style="margin-bottom:1rem">
            <input type="hidden" name="informe" value="personas">
            <input type="hidden" name="rango" value="{{ $rango }}">
            <input type="hidden" name="desde" value="{{ $desde->toDateString() }}">
            <input type="hidden" name="hasta" value="{{ $hasta->toDateString() }}">

            <div class="campo">
                <label for="usuarioInforme">Profesional</label>
                <select name="usuario_id" id="usuarioInforme" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($profesionales as $prof)
                        <option value="{{ $prof->id }}" @selected(($profesionalId ?? null) === $prof->id)>
                            {{ $prof->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    @endif

    <p class="tarjeta__ayuda">
        Cada línea del ticket guarda quién realizó el servicio, así que las cifras
        salen de la ejecución real y no de quién cobró.
    </p>

    @if ($datos['profesionales'] === [])
        <p class="campo__pista">No hay ventas en este periodo.</p>
    @else
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Profesional</th><th class="num">Tickets</th>
                        <th class="num">Unidades</th><th class="num">Ventas</th>
                        <th class="num">Comisión</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($datos['profesionales'] as $p)
                    <tr>
                        <td>
                            <span class="punto-color" style="background:{{ $p['color'] }}"></span>
                            {{ $p['etiqueta'] }}
                        </td>
                        <td class="num">{{ $p['tickets'] }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($p['unidades'], 2, ',', '.'), '0'), ',') }}</td>
                        <td class="num">{{ number_format($p['total'], 2, ',', '.') }} €</td>
                        <td class="num">
                            @if ($p['comision_pct'] > 0)
                                {{ number_format($p['comision'], 2, ',', '.') }} €
                                <small style="color:var(--suave)">({{ rtrim(rtrim(number_format($p['comision_pct'], 2, ',', '.'), '0'), ',') }}%)</small>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Por medio de pago</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'medios']) }}"
           class="boton boton--secundario boton--pequeno">CSV</a>
    </div>
    @include('panel.informes.partes.lista', ['datos' => $datos['medios'], 'unidad' => 'veces'])
</div>

{{-- ---------- Envío del informe por correo ---------- --}}
<div class="modal" id="modalProfesionales" hidden>
    <div class="modal__caja" style="max-width:440px">
        <h2>Enviar informe</h2>

        <form method="POST" action="{{ route('panel.documentos.profesionales.enviar') }}">
            @csrf
            <input type="hidden" name="desde" value="{{ $desde->toDateString() }}">
            <input type="hidden" name="hasta" value="{{ $hasta->toDateString() }}">
            <input type="hidden" name="usuario_id" value="{{ $profesionalId ?? '' }}">

            <div class="campo">
                <label for="emailProfesionales">Dirección de correo</label>
                <input type="email" id="emailProfesionales" name="email" required>
                <p class="campo__pista">
                    Se manda el PDF con el mismo periodo y el mismo profesional
                    que ves ahora.
                </p>
            </div>

            <div class="modal__pie">
                <button type="button" class="boton boton--secundario"
                        onclick="document.getElementById('modalProfesionales').hidden = true">
                    Cancelar
                </button>
                <button type="submit" class="boton">Enviar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v=36">
<script>
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.getElementById('modalProfesionales').hidden = true;
    }
});
</script>
@endpush
