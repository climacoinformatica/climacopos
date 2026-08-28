@extends('panel.app')

@section('titulo', 'Diseño del ticket')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Diseño del ticket</h1>
        <p>Cabecera, pie, logotipo y qué se imprime</p>
    </div>
    <a href="{{ route('panel.ajustes.hardware') }}" class="boton boton--secundario">Hardware</a>
</div>

<form method="POST" action="{{ route('panel.ajustes.ticket.guardar') }}" enctype="multipart/form-data">
    @csrf

    <div class="disenador">
        <div class="disenador__campos">

            <div class="tarjeta">
                <h2>General</h2>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="nombre">Nombre del diseño</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="{{ old('nombre', $diseno->nombre) }}">
                    </div>

                    <div class="campo">
                        <label for="ancho_mm">Ancho del papel</label>
                        <select id="ancho_mm" name="ancho_mm" required>
                            <option value="80" @selected(old('ancho_mm', $diseno->ancho_mm) == 80)>80 mm · 48 columnas</option>
                            <option value="58" @selected(old('ancho_mm', $diseno->ancho_mm) == 58)>58 mm · 32 columnas</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="tarjeta">
                <h2>Logotipo</h2>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="logo">Imagen</label>
                        <input type="file" id="logo" name="logo" accept="image/*">
                        <p class="campo__pista">
                            Se convierte a blanco y negro puro: las térmicas no tienen grises.
                            Un logo con degradados sale como una mancha; mejor uno plano y con contraste.
                        </p>
                    </div>

                    <div class="campo">
                        <label for="logo_alineacion">Alineación</label>
                        <select id="logo_alineacion" name="logo_alineacion">
                            @foreach (['IZQUIERDA' => 'Izquierda', 'CENTRO' => 'Centro', 'DERECHA' => 'Derecha'] as $clave => $texto)
                                <option value="{{ $clave }}" @selected(old('logo_alineacion', $diseno->logo_alineacion) === $clave)>
                                    {{ $texto }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo">
                        <label for="logo_ancho_px">Ancho en puntos</label>
                        <input type="number" id="logo_ancho_px" name="logo_ancho_px"
                               min="64" max="576" step="8"
                               value="{{ old('logo_ancho_px', $diseno->logo_ancho_px) }}">
                        <p class="campo__pista">384 para papel de 80 mm, 256 para 58 mm.</p>
                    </div>
                </div>

                @if ($diseno->logo)
                    <p class="campo__pista">Ya hay un logotipo cargado. Sube otro para reemplazarlo.</p>
                @endif
            </div>

            {{-- ---------- Cabecera ---------- --}}
            <div class="tarjeta">
                <h2>Líneas de cabecera</h2>
                <p class="tarjeta__ayuda">
                    Se imprimen arriba del todo, debajo del logotipo. Los datos fiscales
                    (razón social, NIF, dirección) salen automáticamente y no hay que ponerlos aquí.
                </p>

                <div id="cabecera" class="filas-diseno">
                    @foreach (old('cabecera', $diseno->cabecera ?? []) as $i => $fila)
                        @include('panel.ajustes.partes.fila-ticket', ['grupo' => 'cabecera', 'i' => $i, 'fila' => $fila])
                    @endforeach
                </div>

                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-anadir="cabecera">Añadir línea</button>
            </div>

            {{-- ---------- Pie ---------- --}}
            <div class="tarjeta">
                <h2>Líneas de pie</h2>

                <div id="pie" class="filas-diseno">
                    @foreach (old('pie', $diseno->pie ?? []) as $i => $fila)
                        @include('panel.ajustes.partes.fila-ticket', ['grupo' => 'pie', 'i' => $i, 'fila' => $fila])
                    @endforeach
                </div>

                <button type="button" class="boton boton--secundario boton--pequeno"
                        data-anadir="pie">Añadir línea</button>
            </div>

            {{-- ---------- Contenido ---------- --}}
            <div class="tarjeta">
                <h2>Qué se imprime</h2>

                @foreach ([
                    'mostrar_cliente'           => ['Nombre del cliente', 'Si el ticket lleva cliente asignado.'],
                    'mostrar_profesional'       => ['Profesional de cada línea', 'Útil cuando trabajan varias personas.'],
                    'mostrar_desglose_impuesto' => ['Desglose de base e impuesto', 'Obligatorio en factura simplificada.'],
                    'mostrar_qr_reserva'        => ['QR para reservar la próxima cita', 'Lleva al portal de reservas.'],
                    'mostrar_qr_verifactu'      => ['QR de VERI*FACTU', 'Se activará cuando esté la fase fiscal.'],
                    'cortar_papel'              => ['Cortar el papel al terminar', 'Desactívalo si la impresora no tiene cortador.'],
                    'abrir_cajon_efectivo'      => ['Abrir el cajón si hubo efectivo', 'No se abre en cobros con tarjeta.'],
                ] as $campo => [$titulo, $ayuda])
                    <div class="casilla">
                        <input type="checkbox" id="{{ $campo }}" name="{{ $campo }}" value="1"
                               @checked(old($campo, $diseno->{$campo}))>
                        <div>
                            <label for="{{ $campo }}">{{ $titulo }}</label>
                            <small>{{ $ayuda }}</small>
                        </div>
                    </div>
                @endforeach

                <div class="campo" style="margin-top:1rem">
                    <label for="texto_legal">Texto legal al final</label>
                    <textarea id="texto_legal" name="texto_legal">{{ old('texto_legal', $diseno->texto_legal) }}</textarea>
                </div>

                <div class="campo">
                    <label for="lineas_finales">Líneas en blanco al final</label>
                    <input type="number" id="lineas_finales" name="lineas_finales" min="0" max="10"
                           value="{{ old('lineas_finales', $diseno->lineas_finales) }}">
                    <p class="campo__pista">
                        Para que el corte no se coma la última línea. Con 4 suele bastar.
                    </p>
                </div>
            </div>

            <button type="submit" class="boton">Guardar diseño</button>
        </div>

        {{-- ---------- Vista previa ---------- --}}
        <aside class="disenador__previa">
            <h3>Vista previa</h3>
            <div class="papel" id="papel"></div>
            <p class="campo__pista">
                Aproximada. El resultado exacto depende de la fuente de la impresora;
                para verlo de verdad, usa «Imprimir prueba» en Hardware.
            </p>
        </aside>
    </div>
</form>

<template id="plantillaFila">
    @include('panel.ajustes.partes.fila-ticket', ['grupo' => '__GRUPO__', 'i' => '__I__', 'fila' => []])
</template>

@push('scripts')
<script>
(function () {
    let contadores = {
        cabecera: {{ count(old('cabecera', $diseno->cabecera ?? [])) }},
        pie:      {{ count(old('pie', $diseno->pie ?? [])) }}
    };

    document.querySelectorAll('[data-anadir]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const grupo = this.dataset.anadir;
            const html = document.getElementById('plantillaFila').innerHTML
                .replaceAll('__GRUPO__', grupo)
                .replaceAll('__I__', contadores[grupo]++);

            const envoltorio = document.createElement('div');
            envoltorio.innerHTML = html;
            const fila = envoltorio.firstElementChild;

            document.getElementById(grupo).appendChild(fila);
            engancharFila(fila);
            pintarPrevia();
        });
    });

    function engancharFila(fila) {
        fila.querySelector('[data-quitar]')?.addEventListener('click', function () {
            fila.remove();
            pintarPrevia();
        });

        fila.querySelectorAll('input, select').forEach(function (campo) {
            campo.addEventListener('input', pintarPrevia);
            campo.addEventListener('change', pintarPrevia);
        });
    }

    document.querySelectorAll('.fila-diseno').forEach(engancharFila);

    // --- Vista previa
    function pintarPrevia() {
        const columnas = parseInt(document.getElementById('ancho_mm').value) === 58 ? 32 : 48;
        const papel = document.getElementById('papel');
        papel.style.width = (columnas * 7.4) + 'px';

        let lineas = [];

        function volcar(grupo) {
            document.querySelectorAll('#' + grupo + ' .fila-diseno').forEach(function (fila) {
                const texto = fila.querySelector('[name$="[texto]"]').value;
                if (!texto) return;

                const alineacion = fila.querySelector('[name$="[alineacion]"]').value;
                const negrita = fila.querySelector('[name$="[negrita]"]').checked;
                const dobleAlto = fila.querySelector('[name$="[doble_alto]"]').checked;
                const dobleAncho = fila.querySelector('[name$="[doble_ancho]"]').checked;

                lineas.push({ texto, alineacion, negrita, dobleAlto, dobleAncho });
            });
        }

        volcar('cabecera');
        lineas.push({ separador: true });
        lineas.push({ texto: 'A-000001            23/08/2026 12:30', alineacion: 'IZQUIERDA' });
        lineas.push({ separador: true });
        lineas.push({ texto: '1    Corte de senora              22,00', alineacion: 'IZQUIERDA' });
        lineas.push({ texto: '1    Champu profesional           18,00', alineacion: 'IZQUIERDA' });
        lineas.push({ separador: true });
        lineas.push({ texto: 'TOTAL                             40,00', alineacion: 'IZQUIERDA', negrita: true });
        lineas.push({ separador: true });
        volcar('pie');

        papel.innerHTML = lineas.map(function (l) {
            if (l.separador) {
                return '<div class="papel__linea">' + '-'.repeat(columnas) + '</div>';
            }

            let clases = 'papel__linea';
            if (l.negrita) clases += ' papel__linea--negrita';
            if (l.dobleAlto) clases += ' papel__linea--alto';
            if (l.dobleAncho) clases += ' papel__linea--ancho';
            if (l.alineacion === 'CENTRO') clases += ' papel__linea--centro';
            if (l.alineacion === 'DERECHA') clases += ' papel__linea--derecha';

            return '<div class="' + clases + '">' + escapar(l.texto) + '</div>';
        }).join('');
    }

    function escapar(t) {
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    document.getElementById('ancho_mm').addEventListener('change', pintarPrevia);
    pintarPrevia();
})();
</script>
<style>
.disenador { display: grid; grid-template-columns: 1fr 400px; gap: 1.25rem; align-items: start; }

.filas-diseno { display: flex; flex-direction: column; gap: .5rem; margin-bottom: .75rem; }
.fila-diseno {
    display: grid; grid-template-columns: 1fr 120px auto auto;
    gap: .5rem; align-items: center;
    background: var(--panel2); border: 1px solid var(--borde);
    border-radius: 9px; padding: .5rem;
}
.fila-diseno input[type=text], .fila-diseno select { margin: 0; }
.fila-diseno__estilos { display: flex; gap: .5rem; font-size: .7rem; color: var(--suave); }
.fila-diseno__estilos label { display: flex; align-items: center; gap: .2rem; cursor: pointer; }
.fila-diseno__quitar {
    background: transparent; border: 1px solid var(--borde); border-radius: 7px;
    color: var(--suave); cursor: pointer; padding: .35rem .6rem;
}

.disenador__previa { position: sticky; top: 90px; }
.disenador__previa h3 { font-size: .85rem; color: var(--suave); margin-bottom: .6rem; }

.papel {
    background: #fdfdf8; color: #111;
    padding: 1rem .6rem;
    border-radius: 3px;
    font-family: "Courier New", monospace;
    font-size: 11px; line-height: 1.35;
    white-space: pre;
    overflow-x: auto;
    box-shadow: 0 4px 18px rgba(0,0,0,.4);
    max-width: 100%;
}
.papel__linea { min-height: 1.35em; }
.papel__linea--negrita { font-weight: 700; }
.papel__linea--alto { font-size: 16px; line-height: 1.2; }
.papel__linea--ancho { letter-spacing: 3px; }
.papel__linea--centro { text-align: center; }
.papel__linea--derecha { text-align: right; }

@media (max-width: 1100px) {
    .disenador { grid-template-columns: 1fr; }
    .disenador__previa { position: static; }
    .fila-diseno { grid-template-columns: 1fr 1fr; }
}
</style>
@endpush

@endsection
