@extends('panel.app')

@section('titulo', 'Nueva cita')

@section('contenido')

<div class="titulo-pagina">
    <h1>Nueva cita</h1>
    <a href="{{ route('panel.agenda', ['fecha' => $fecha->toDateString()]) }}"
       class="boton boton--secundario">Volver a la agenda</a>
</div>

<form method="POST" action="{{ route('panel.agenda.cita.guardar') }}" id="formCita">
    @csrf

    <div class="tarjeta" style="max-width:720px">
        <h2>Cliente</h2>

        <div class="campo">
            <label for="buscarCliente">Buscar cliente</label>
            <input type="text" id="buscarCliente" autocomplete="off"
                   placeholder="Nombre, teléfono o código...">
            <div id="resultadosCliente" class="autocompletar" hidden></div>
        </div>

        <input type="hidden" name="cliente_id" id="clienteId" value="{{ old('cliente_id') }}">

        <div id="clienteSeleccionado" class="aviso aviso--info" hidden></div>

        <div id="clienteNuevo">
            <div class="rejilla-campos">
                <div class="campo">
                    <label for="cliente_nombre">Nombre</label>
                    <input type="text" id="cliente_nombre" name="cliente_nombre"
                           value="{{ old('cliente_nombre') }}">
                </div>
                <div class="campo">
                    <label for="cliente_telefono">Teléfono</label>
                    <input type="text" id="cliente_telefono" name="cliente_telefono"
                           value="{{ old('cliente_telefono') }}">
                    <p class="campo__pista">Si ya existe con ese teléfono, se reutiliza su ficha.</p>
                </div>
                <div class="campo">
                    <label for="cliente_email">Email</label>
                    <input type="text" id="cliente_email" name="cliente_email"
                           value="{{ old('cliente_email') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="tarjeta" style="max-width:720px">
        <h2>Servicios</h2>
        <p class="tarjeta__ayuda">
            Se encadenan uno detrás de otro. Si un servicio tiene pausa, el siguiente
            empieza cuando termina del todo, no durante la espera.
        </p>

        <div id="servicios"></div>

        <button type="button" class="boton boton--secundario boton--pequeno" id="anadirServicio">
            Añadir servicio
        </button>
    </div>

    <div class="tarjeta" style="max-width:720px">
        <h2>Cuándo</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="fecha">Fecha</label>
                <input type="date" id="fecha" name="fecha" required
                       value="{{ old('fecha', $fecha->toDateString()) }}">
            </div>

            <div class="campo">
                <label for="hora">Hora</label>
                <select id="hora" name="hora" required>
                    <option value="">— Elige servicio primero —</option>
                </select>
                <p class="campo__pista" id="avisoHuecos"></p>
            </div>
        </div>

        <div class="campo">
            <label for="notas">Notas</label>
            <textarea id="notas" name="notas">{{ old('notas') }}</textarea>
        </div>
    </div>

    <div style="display:flex;gap:.75rem">
        <button type="submit" class="boton">Crear cita</button>
        <a href="{{ route('panel.agenda', ['fecha' => $fecha->toDateString()]) }}"
           class="boton boton--secundario">Cancelar</a>
    </div>
</form>

<template id="plantillaServicio">
    <div class="servicio-fila">
        <div class="campo">
            <label>Servicio</label>
            <select class="campoArticulo" required>
                <option value="">— Elige —</option>
                @foreach ($familias as $familia)
                    <optgroup label="{{ $familia->nombre }}">
                        @foreach ($familia->articulos->where('activo', true) as $articulo)
                            <option value="{{ $articulo->id }}"
                                    data-duracion="{{ $articulo->duracionTotal() }}">
                                {{ $articulo->nombre }}
                                ({{ $articulo->duracionTotal() }} min · {{ number_format($articulo->precio, 2, ',', '.') }} €)
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <div class="campo">
            <label>Profesional</label>
            <select class="campoProfesional">
                <option value="">— El primero libre —</option>
                @foreach ($profesionales as $profesional)
                    <option value="{{ $profesional->id }}">{{ $profesional->nombre }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" class="quitarServicio">&times;</button>
    </div>
</template>

@push('scripts')
<script>
(function () {
    const contenedor = document.getElementById('servicios');
    const plantilla  = document.getElementById('plantillaServicio');
    const selectHora = document.getElementById('hora');
    const avisoHuecos = document.getElementById('avisoHuecos');
    const campoFecha = document.getElementById('fecha');
    let indice = 0;

    function anadirServicio(articuloId, usuarioId) {
        const nodo = plantilla.content.cloneNode(true);
        const fila = nodo.querySelector('.servicio-fila');
        const i = indice++;

        const articulo = fila.querySelector('.campoArticulo');
        const profesional = fila.querySelector('.campoProfesional');

        articulo.name = 'servicios[' + i + '][articulo_id]';
        profesional.name = 'servicios[' + i + '][usuario_id]';

        if (articuloId) articulo.value = articuloId;
        if (usuarioId) profesional.value = usuarioId;

        articulo.addEventListener('change', cargarHuecos);
        profesional.addEventListener('change', cargarHuecos);
        fila.querySelector('.quitarServicio').addEventListener('click', function () {
            fila.remove();
            cargarHuecos();
        });

        contenedor.appendChild(nodo);
    }

    document.getElementById('anadirServicio').addEventListener('click', function () {
        anadirServicio();
    });

    // --- Huecos disponibles del primer servicio
    async function cargarHuecos() {
        const primero = contenedor.querySelector('.servicio-fila');
        if (!primero) return;

        const articuloId = primero.querySelector('.campoArticulo').value;
        const usuarioId  = primero.querySelector('.campoProfesional').value;

        if (!articuloId) {
            selectHora.innerHTML = '<option value="">— Elige servicio primero —</option>';
            return;
        }

        selectHora.innerHTML = '<option value="">Cargando...</option>';
        avisoHuecos.textContent = '';

        const parametros = new URLSearchParams({
            fecha: campoFecha.value,
            articulo_id: articuloId
        });
        if (usuarioId) parametros.append('usuario_id', usuarioId);

        try {
            const respuesta = await fetch('{{ route('panel.agenda.huecos') }}?' + parametros);
            const datos = await respuesta.json();

            const seleccionada = '{{ $horaSugerida }}';
            selectHora.innerHTML = '';

            if (datos.huecos.length === 0) {
                selectHora.innerHTML = '<option value="">Sin huecos ese día</option>';
                avisoHuecos.textContent = 'Prueba otra fecha, otro profesional, o revisa los horarios.';
                return;
            }

            selectHora.appendChild(new Option('— Elige hora —', ''));
            datos.huecos.forEach(function (hora) {
                const opcion = new Option(hora, hora);
                if (hora === seleccionada) opcion.selected = true;
                selectHora.appendChild(opcion);
            });

            avisoHuecos.textContent = datos.huecos.length + ' hueco(s) · duración ' + datos.duracion + ' min';
        } catch (error) {
            selectHora.innerHTML = '<option value="">Error al consultar</option>';
        }
    }

    campoFecha.addEventListener('change', cargarHuecos);

    // --- Autocompletar cliente
    const buscador = document.getElementById('buscarCliente');
    const resultados = document.getElementById('resultadosCliente');
    const campoClienteId = document.getElementById('clienteId');
    const bloqueNuevo = document.getElementById('clienteNuevo');
    const bloqueElegido = document.getElementById('clienteSeleccionado');
    let temporizador;

    buscador.addEventListener('input', function () {
        clearTimeout(temporizador);
        const texto = this.value.trim();

        if (texto.length < 2) { resultados.hidden = true; return; }

        temporizador = setTimeout(async function () {
            const respuesta = await fetch('{{ route('panel.clientes.buscar') }}?q=' + encodeURIComponent(texto));
            const lista = await respuesta.json();

            resultados.innerHTML = '';

            if (lista.length === 0) {
                resultados.hidden = true;
                return;
            }

            lista.forEach(function (cliente) {
                const fila = document.createElement('button');
                fila.type = 'button';
                fila.className = 'autocompletar__fila';
                fila.innerHTML = '<strong>' + cliente.nombre + '</strong>' +
                    (cliente.telefono ? '<span>' + cliente.telefono + '</span>' : '') +
                    (cliente.avisos ? '<em>' + cliente.avisos + '</em>' : '');
                fila.addEventListener('click', function () {
                    campoClienteId.value = cliente.id;
                    bloqueElegido.textContent = 'Cliente: ' + cliente.nombre +
                        (cliente.telefono ? ' · ' + cliente.telefono : '');
                    bloqueElegido.hidden = false;
                    bloqueNuevo.hidden = true;
                    resultados.hidden = true;
                    buscador.value = '';
                });
                resultados.appendChild(fila);
            });

            resultados.hidden = false;
        }, 250);
    });

    // Arranque
    anadirServicio(null, '{{ $usuarioId }}');
})();
</script>
<style>
.servicio-fila { display: grid; grid-template-columns: 2fr 1.2fr auto; gap: .6rem; align-items: end; margin-bottom: .75rem; }
.servicio-fila .campo { margin-bottom: 0; }
.servicio-fila .quitarServicio {
    background: transparent; border: 1px solid var(--borde); border-radius: 8px;
    color: var(--suave); cursor: pointer; padding: .6rem .8rem;
}
.autocompletar {
    background: var(--panel2); border: 1px solid var(--borde);
    border-radius: 9px; margin-top: .25rem; overflow: hidden;
}
.autocompletar__fila {
    display: block; width: 100%; text-align: left;
    padding: .5rem .75rem; background: transparent; border: 0;
    border-bottom: 1px solid var(--borde); color: var(--texto); cursor: pointer;
}
.autocompletar__fila:hover { background: var(--fondo); }
.autocompletar__fila span { color: var(--suave); font-size: .8rem; margin-left: .5rem; }
.autocompletar__fila em { color: var(--aviso); font-size: .72rem; margin-left: .5rem; font-style: normal; }
@media (max-width: 640px) { .servicio-fila { grid-template-columns: 1fr 1fr auto; } }
</style>
@endpush

@endsection
