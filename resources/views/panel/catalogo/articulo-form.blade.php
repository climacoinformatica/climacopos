@extends('panel.app')

@section('titulo', $articulo->exists ? $articulo->nombre : 'Nuevo artículo')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $articulo->exists ? $articulo->nombre : 'Nuevo artículo' }}</h1>
        @if ($articulo->exists)
            <p>Creado el {{ $articulo->created_at->format('d/m/Y') }}</p>
        @endif
    </div>
    <a href="{{ route('panel.catalogo.articulos') }}" class="boton boton--secundario">Volver</a>
</div>

<form method="POST"
      {{-- Al editar, la ruta con {articulo}: ver nota en familia-form --}}
      action="{{ $articulo->exists
                 ? route('panel.catalogo.articulos.guardar.editar', $articulo)
                 : route('panel.catalogo.articulos.guardar') }}"
      enctype="multipart/form-data">
    @csrf

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta">
        <h2>Datos básicos</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required
                       value="{{ old('nombre', $articulo->nombre) }}">
            </div>

            <div class="campo">
                <label for="familia_id">Familia *</label>
                <select id="familia_id" name="familia_id" required>
                    <option value="">— Elige familia —</option>
                    @foreach ($familias as $familia)
                        <option value="{{ $familia->id }}"
                                @selected(old('familia_id', $articulo->familia_id) == $familia->id)>
                            {{ $familia->nombreCompleto() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="tipo">Tipo *</label>
                <select id="tipo" name="tipo" required>
                    @foreach (['SERVICIO' => 'Servicio', 'PRODUCTO' => 'Producto', 'BONO' => 'Bono de sesiones', 'PACK' => 'Pack'] as $clave => $texto)
                        <option value="{{ $clave }}" @selected(old('tipo', $articulo->tipo) === $clave)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="codigo">Código interno</label>
                <input type="text" id="codigo" name="codigo" value="{{ old('codigo', $articulo->codigo) }}">
            </div>
        </div>

        <div class="campo">
            <label for="descripcion">Descripción interna</label>
            <textarea id="descripcion" name="descripcion">{{ old('descripcion', $articulo->descripcion) }}</textarea>
            <p class="campo__pista">Solo la ve el equipo. No aparece en el portal de reservas.</p>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="0" max="999"
                       value="{{ old('orden', $articulo->orden ?? 0) }}">
                <p class="campo__pista">Menor número, antes en la lista.</p>
            </div>

            <div class="campo">
                <label for="color">Color en el TPV</label>
                <input type="color" id="color" name="color"
                       value="{{ old('color', $articulo->color ?? '#6366f1') }}">
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="activo" name="activo" value="1"
                   @checked(old('activo', $articulo->activo ?? true))>
            <div>
                <label for="activo">Activo</label>
                <small>Los inactivos no se pueden vender ni reservar, pero se conservan en el histórico.</small>
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta">
        <h2>Precio</h2>
        <p class="tarjeta__ayuda">
            El precio se introduce SIEMPRE con impuesto incluido: es como se anuncia al cliente
            y como se teclea en el TPV. La base imponible se calcula sola.
        </p>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="precio">Precio con impuesto *</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" required
                       value="{{ old('precio', $articulo->precio ?? '0.00') }}">
            </div>

            <div class="campo">
                <label for="impuesto_pct">{{ tenant('regimen_fiscal') === 'IVA' ? 'IVA' : 'IGIC' }} % *</label>
                <input type="number" id="impuesto_pct" name="impuesto_pct" step="0.01" min="0" max="99.99" required
                       value="{{ old('impuesto_pct', $articulo->impuesto_pct ?? 7) }}">
            </div>

            <div class="campo">
                <label>Base imponible</label>
                <input type="text" id="baseCalculada" readonly
                       style="background:transparent;color:var(--suave)">
            </div>

            <div class="campo">
                <label for="coste">Coste (opcional)</label>
                <input type="number" id="coste" name="coste" step="0.01" min="0"
                       value="{{ old('coste', $articulo->coste) }}">
                <p class="campo__pista">Para calcular márgenes en los informes.</p>
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta" data-solo="SERVICIO,BONO,PACK">
        <h2>Duración y agenda</h2>
        <p class="tarjeta__ayuda">
            La pausa es el hueco en que el profesional queda libre y puede atender a otro cliente.
            Un tinte típico: 20 min aplicando, 30 min de espera, 15 min de lavado y secado.
        </p>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="duracion_min">Duración activa (min)</label>
                <input type="number" id="duracion_min" name="duracion_min" min="0" max="600" step="5"
                       value="{{ old('duracion_min', $articulo->duracion_min ?? 30) }}">
            </div>

            <div class="campo">
                <label for="tiempo_pausa_min">Pausa (min)</label>
                <input type="number" id="tiempo_pausa_min" name="tiempo_pausa_min" min="0" max="600" step="5"
                       value="{{ old('tiempo_pausa_min', $articulo->tiempo_pausa_min ?? 0) }}">
            </div>

            <div class="campo">
                <label for="tiempo_final_min">Tiempo final (min)</label>
                <input type="number" id="tiempo_final_min" name="tiempo_final_min" min="0" max="600" step="5"
                       value="{{ old('tiempo_final_min', $articulo->tiempo_final_min ?? 0) }}">
            </div>

            <div class="campo">
                <label for="recurso_id">Recurso necesario</label>
                <select id="recurso_id" name="recurso_id">
                    <option value="">— Ninguno —</option>
                    @foreach ($recursos as $recurso)
                        <option value="{{ $recurso->id }}" @selected(old('recurso_id', $articulo->recurso_id) == $recurso->id)>
                            {{ $recurso->nombre }} ({{ $recurso->cantidad }})
                        </option>
                    @endforeach
                </select>
                <p class="campo__pista">Cabina, lavacabezas, aparato...</p>
            </div>
        </div>

        <p class="campo__pista" id="resumenDuracion" style="margin-top:.5rem"></p>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta" data-solo="SERVICIO,PACK">
        <h2>Quién lo realiza</h2>
        <p class="tarjeta__ayuda">
            Si no marcas a nadie, el servicio lo puede hacer cualquier profesional.
        </p>

        @if ($profesionales->isEmpty())
            <p class="aviso aviso--info">
                Todavía no hay profesionales dados de alta. Créalos con
                <code>php artisan climacopos:crear-usuario</code> usando la opción <code>--profesional</code>.
            </p>
        @else
            <div class="chips">
                @foreach ($profesionales as $profesional)
                    <label class="chip">
                        <input type="checkbox" name="profesionales[]" value="{{ $profesional->id }}"
                               @checked(in_array($profesional->id, old('profesionales', $articulo->profesionales->pluck('id')->all())))>
                        <span>{{ $profesional->nombre }}</span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta" data-solo="PRODUCTO">
        <h2>Stock</h2>

        <div class="casilla">
            <input type="checkbox" id="control_stock" name="control_stock" value="1"
                   @checked(old('control_stock', $articulo->control_stock))>
            <div>
                <label for="control_stock">Controlar existencias</label>
                <small>Cada venta descuenta del stock y avisa al llegar al mínimo.</small>
            </div>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="stock">Stock actual</label>
                <input type="number" id="stock" name="stock" step="0.001"
                       value="{{ old('stock', $articulo->stock ?? 0) }}">
            </div>
            <div class="campo">
                <label for="stock_min">Stock mínimo</label>
                <input type="number" id="stock_min" name="stock_min" step="0.001" min="0"
                       value="{{ old('stock_min', $articulo->stock_min ?? 0) }}">
            </div>
            <div class="campo">
                <label for="codigo_barras">Código de barras</label>
                <input type="text" id="codigo_barras" name="codigo_barras"
                       value="{{ old('codigo_barras', $articulo->codigo_barras) }}">
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta" data-solo="BONO">
        <h2>Bono</h2>
        <div class="rejilla-campos">
            <div class="campo">
                <label for="sesiones">Número de sesiones</label>
                <input type="number" id="sesiones" name="sesiones" min="1" max="999"
                       value="{{ old('sesiones', $articulo->sesiones) }}">
            </div>
            <div class="campo">
                <label for="caducidad_dias">Caducidad (días)</label>
                <input type="number" id="caducidad_dias" name="caducidad_dias" min="1" max="3650"
                       value="{{ old('caducidad_dias', $articulo->caducidad_dias) }}">
                <p class="campo__pista">Vacío = sin caducidad.</p>
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta" data-solo="SERVICIO,PACK">
        <h2>Reserva online</h2>

        <div class="casilla">
            <input type="checkbox" id="permite_reserva_online" name="permite_reserva_online" value="1"
                   @checked(old('permite_reserva_online', $articulo->permite_reserva_online ?? true))>
            <div>
                <label for="permite_reserva_online">Reservable desde el portal</label>
                <small>Aparece en {{ tenant()->slug }}.{{ config('climacopos.dominio_base') }}</small>
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="requiere_confirmacion" name="requiere_confirmacion" value="1"
                   @checked(old('requiere_confirmacion', $articulo->requiere_confirmacion ?? true))>
            <div>
                <label for="requiere_confirmacion">Requiere confirmación manual</label>
                <small>La reserva queda pendiente hasta que alguien la acepte desde el panel.</small>
            </div>
        </div>

        <div class="campo">
            <label for="descripcion_online">Descripción para el cliente</label>
            <textarea id="descripcion_online" name="descripcion_online">{{ old('descripcion_online', $articulo->descripcion_online) }}</textarea>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="politica_pago">Pago al reservar</label>
                <select id="politica_pago" name="politica_pago">
                    <option value="NINGUNO" @selected(old('politica_pago', $articulo->politica_pago) === 'NINGUNO')>Sin pago</option>
                    <option value="FIANZA"  @selected(old('politica_pago', $articulo->politica_pago) === 'FIANZA')>Fianza</option>
                    <option value="TOTAL"   @selected(old('politica_pago', $articulo->politica_pago) === 'TOTAL')>Pago completo</option>
                </select>
            </div>

            <div class="campo">
                <label for="modo_fianza">Fianza como</label>
                <select id="modo_fianza" name="modo_fianza">
                    <option value="IMPORTE" @selected(is_null($articulo->fianza_pct))>Importe fijo</option>
                    <option value="PCT"     @selected(! is_null($articulo->fianza_pct))>Porcentaje</option>
                </select>
            </div>

            <div class="campo">
                <label for="fianza_importe">Importe de fianza (€)</label>
                <input type="number" id="fianza_importe" name="fianza_importe" step="0.01" min="0"
                       value="{{ old('fianza_importe', $articulo->fianza_importe) }}">
            </div>

            <div class="campo">
                <label for="fianza_pct">Fianza (%)</label>
                <input type="number" id="fianza_pct" name="fianza_pct" step="0.01" min="0" max="100"
                       value="{{ old('fianza_pct', $articulo->fianza_pct) }}">
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta">
        <h2>Características</h2>
        <p class="tarjeta__ayuda">Marca, formato, tono, tipo de cabello... Se muestran en la ficha del portal.</p>

        <div id="atributos">
            @php $atributosActuales = old('atributos', $articulo->atributos->map(fn ($a) => ['clave' => $a->clave, 'valor' => $a->valor])->all()); @endphp
            @foreach ($atributosActuales as $i => $atributo)
                <div class="fila-dinamica">
                    <input type="text" name="atributos[{{ $i }}][clave]" placeholder="Característica"
                           value="{{ $atributo['clave'] ?? '' }}" list="sugerenciasAtributos">
                    <input type="text" name="atributos[{{ $i }}][valor]" placeholder="Valor"
                           value="{{ $atributo['valor'] ?? '' }}">
                    <button type="button" onclick="this.parentElement.remove()">&times;</button>
                </div>
            @endforeach
        </div>

        <datalist id="sugerenciasAtributos">
            @foreach (\App\Models\ArticuloAtributo::SUGERENCIAS as $sugerencia)
                <option value="{{ $sugerencia }}"></option>
            @endforeach
        </datalist>

        <button type="button" class="boton boton--secundario boton--pequeno" id="anadirAtributo">
            Añadir característica
        </button>
    </div>

    {{-- ---------------------------------------------------------- --}}
    <div class="tarjeta">
        <h2>Fotos</h2>

        @if ($articulo->exists && $articulo->fotos->isNotEmpty())
            <div class="fotos" style="margin-bottom:1rem">
                @foreach ($articulo->fotos as $foto)
                    <div class="foto {{ $foto->principal ? 'foto--principal' : '' }}">
                        <img src="{{ $foto->urlMini() }}" alt="{{ $foto->alt }}">
                        @if ($foto->principal)
                            <span class="foto__insignia">Principal</span>
                        @endif
                        <div class="foto__acciones">
                            @unless ($foto->principal)
                                <button type="button" onclick="accionFoto('{{ route('panel.catalogo.fotos.principal', $foto) }}')">
                                    Principal
                                </button>
                            @endunless
                            <button type="button" onclick="accionFoto('{{ route('panel.catalogo.fotos.borrar', $foto) }}', true)">
                                Borrar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Sin fotos: se dice claramente, en vez de dejar el hueco vacio --}}
            <div class="vista-imagen">
                <span class="vista-imagen__vacio">Sin imagen</span>
            </div>
        @endif

        <div class="campo">
            <label for="fotos">Añadir fotos</label>
            <input type="file" id="fotos" name="fotos[]" accept="image/*" multiple>
            <p class="campo__pista">
                Hasta 8 por artículo, 8 MB cada una. Se redimensionan a 1200 px y se genera miniatura
                automáticamente. La primera que subas será la principal.
            </p>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <button type="submit" class="boton">
            {{ $articulo->exists ? 'Guardar cambios' : 'Crear artículo' }}
        </button>
        <a href="{{ route('panel.catalogo.articulos') }}" class="boton boton--secundario">Cancelar</a>
    </div>
</form>

@if ($articulo->exists)
    <form method="POST" action="{{ route('panel.catalogo.articulos.borrar', $articulo) }}"
          style="margin-top:2rem" onsubmit="return confirm('¿Borrar «{{ $articulo->nombre }}»? Los tickets antiguos lo conservarán.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="boton boton--secundario boton--pequeno">Borrar artículo</button>
    </form>
@endif

{{-- Formularios auxiliares para las acciones de foto --}}
<form method="POST" id="formFoto" style="display:none">@csrf</form>

@push('scripts')
<script>
(function () {
    // --- Mostrar solo las secciones que aplican al tipo elegido
    const selectorTipo = document.getElementById('tipo');

    function ajustarSecciones() {
        const tipo = selectorTipo.value;
        document.querySelectorAll('[data-solo]').forEach(function (seccion) {
            const tipos = seccion.dataset.solo.split(',');
            seccion.style.display = tipos.includes(tipo) ? '' : 'none';
        });
    }

    selectorTipo.addEventListener('change', ajustarSecciones);
    ajustarSecciones();

    // --- Base imponible en vivo
    const precio    = document.getElementById('precio');
    const impuesto  = document.getElementById('impuesto_pct');
    const base      = document.getElementById('baseCalculada');

    function calcularBase() {
        const p = parseFloat(precio.value) || 0;
        const i = parseFloat(impuesto.value) || 0;
        const b = p / (1 + i / 100);
        base.value = b.toFixed(2).replace('.', ',') + ' €  (impuesto ' +
                     (p - b).toFixed(2).replace('.', ',') + ' €)';
    }

    precio.addEventListener('input', calcularBase);
    impuesto.addEventListener('input', calcularBase);
    calcularBase();

    // --- Resumen de duración
    const campos = ['duracion_min', 'tiempo_pausa_min', 'tiempo_final_min'].map(function (id) {
        return document.getElementById(id);
    });
    const resumen = document.getElementById('resumenDuracion');

    function calcularDuracion() {
        if (!resumen) return;
        const [activa, pausa, final] = campos.map(function (c) { return parseInt(c.value) || 0; });
        const total = activa + pausa + final;
        let texto = 'La cita ocupa ' + total + ' min en la agenda.';
        if (pausa > 0) {
            texto += ' El profesional queda libre ' + pausa + ' min durante la pausa, ' +
                     'así que puede atender a otro cliente en ese hueco.';
        }
        resumen.textContent = texto;
    }

    campos.forEach(function (c) { if (c) c.addEventListener('input', calcularDuracion); });
    calcularDuracion();

    // --- Características dinámicas
    let contador = {{ count($atributosActuales) }};

    document.getElementById('anadirAtributo').addEventListener('click', function () {
        const fila = document.createElement('div');
        fila.className = 'fila-dinamica';
        fila.innerHTML =
            '<input type="text" name="atributos[' + contador + '][clave]" placeholder="Característica" list="sugerenciasAtributos">' +
            '<input type="text" name="atributos[' + contador + '][valor]" placeholder="Valor">' +
            '<button type="button">&times;</button>';
        fila.querySelector('button').addEventListener('click', function () { fila.remove(); });
        document.getElementById('atributos').appendChild(fila);
        contador++;
    });
})();

function accionFoto(url, confirmar) {
    if (confirmar && !confirm('¿Borrar esta foto?')) return;
    const form = document.getElementById('formFoto');
    form.action = url;
    form.submit();
}
</script>
@endpush

@endsection
