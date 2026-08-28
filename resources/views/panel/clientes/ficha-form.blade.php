@extends('panel.app')

@section('titulo', 'Ficha técnica')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $ficha->exists ? 'Editar ficha técnica' : 'Nueva ficha técnica' }}</h1>
        <p>{{ $cliente->nombreCompleto() }}</p>
    </div>
    <a href="{{ route('panel.clientes.ver', $cliente) }}" class="boton boton--secundario">Volver</a>
</div>

@if ($esCopia)
    <p class="aviso aviso--info">
        Copiada de una ficha anterior. Revisa la fórmula antes de guardar:
        el cabello cambia entre visitas y lo que funcionó hace dos meses
        puede necesitar ajuste.
    </p>
@endif

@if ($cliente->alergias)
    <p class="aviso aviso--error">
        <strong>Alergias:</strong> {{ $cliente->alergias }}
    </p>
@endif

<form method="POST"
      action="{{ $ficha->exists
                 ? route('panel.clientes.ficha.guardar.editar', [$cliente, $ficha])
                 : route('panel.clientes.ficha.guardar', $cliente) }}">
    @csrf

    <div class="tarjeta" style="max-width:840px">
        <div class="rejilla-campos">
            <div class="campo">
                <label for="tipo">Tipo *</label>
                <select id="tipo" name="tipo" required>
                    @foreach (\App\Models\FichaTecnica::TIPOS as $clave => $texto)
                        <option value="{{ $clave }}" @selected(old('tipo', $ficha->tipo) === $clave)>
                            {{ $texto }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" maxlength="160"
                       value="{{ old('titulo', $ficha->titulo) }}"
                       placeholder="Cobrizo con reflejos">
            </div>

            <div class="campo">
                <label for="fecha">Fecha</label>
                <input type="datetime-local" id="fecha" name="fecha"
                       value="{{ old('fecha', ($ficha->fecha ?? now())->format('Y-m-d\TH:i')) }}">
            </div>
        </div>
    </div>

    {{-- ---------- Fórmula ---------- --}}
    <div class="tarjeta" style="max-width:840px" id="bloqueFormula">
        <h2>Fórmula</h2>
        <p class="tarjeta__ayuda">
            Anota lo que se ha mezclado y en qué cantidad. Es lo que permite
            repetir exactamente el mismo resultado dentro de dos meses.
        </p>

        <div id="componentes">
            @php
                $componentes = old('formula', $ficha->formula ?? []);
                if (empty($componentes)) $componentes = [[], []];
            @endphp

            @foreach ($componentes as $i => $componente)
                <div class="componente">
                    <input type="text" name="formula[{{ $i }}][marca]" placeholder="Marca"
                           value="{{ $componente['marca'] ?? '' }}">
                    <input type="text" name="formula[{{ $i }}][tono]" placeholder="Tono"
                           value="{{ $componente['tono'] ?? '' }}">
                    <input type="number" name="formula[{{ $i }}][cantidad]" placeholder="Cant."
                           step="0.1" min="0" value="{{ $componente['cantidad'] ?? '' }}">
                    <select name="formula[{{ $i }}][unidad]">
                        @foreach (['g' => 'g', 'ml' => 'ml', 'cm' => 'cm'] as $clave => $texto)
                            <option value="{{ $clave }}" @selected(($componente['unidad'] ?? 'g') === $clave)>
                                {{ $texto }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" class="componente__quitar" data-quitar>&times;</button>
                </div>
            @endforeach
        </div>

        <button type="button" class="boton boton--secundario boton--pequeno" id="anadirComponente">
            Añadir componente
        </button>

        <div class="rejilla-campos" style="margin-top:1.25rem">
            <div class="campo">
                <label for="oxigenante">Oxigenante</label>
                <input type="text" id="oxigenante" name="oxigenante" maxlength="40"
                       value="{{ old('oxigenante', $ficha->oxigenante) }}"
                       placeholder="20 vol">
            </div>

            <div class="campo">
                <label for="tiempo_pose_min">Tiempo de pose (min)</label>
                <input type="number" id="tiempo_pose_min" name="tiempo_pose_min" min="1" max="600"
                       value="{{ old('tiempo_pose_min', $ficha->tiempo_pose_min) }}">
            </div>
        </div>
    </div>

    {{-- ---------- Proceso y resultado ---------- --}}
    <div class="tarjeta" style="max-width:840px">
        <h2>Proceso y resultado</h2>

        <div class="campo">
            <label for="proceso">Cómo se hizo</label>
            <textarea id="proceso" name="proceso" rows="3"
                      placeholder="Aplicación en raíz, después medios y puntas...">{{ old('proceso', $ficha->proceso) }}</textarea>
        </div>

        <div class="campo">
            <label for="resultado">Resultado</label>
            <textarea id="resultado" name="resultado" rows="2">{{ old('resultado', $ficha->resultado) }}</textarea>
        </div>

        <div class="campo">
            <label for="observaciones">Observaciones para la próxima vez</label>
            <textarea id="observaciones" name="observaciones" rows="2"
                      placeholder="Quedó algo oscuro, subir medio tono">{{ old('observaciones', $ficha->observaciones) }}</textarea>
            <p class="campo__pista">
                Lo más útil de toda la ficha: lo que habría que corregir.
            </p>
        </div>

        <div class="campo">
            <label for="valoracion">Valoración del resultado</label>
            <select id="valoracion" name="valoracion">
                <option value="">— Sin valorar —</option>
                @foreach ([5 => 'Perfecto', 4 => 'Bien', 3 => 'Correcto',
                           2 => 'Mejorable', 1 => 'No repetir'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected(old('valoracion', $ficha->valoracion) == $valor)>
                        {{ str_repeat('★', $valor) }} {{ $texto }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <button type="submit" class="boton">
        {{ $ficha->exists ? 'Guardar cambios' : 'Guardar ficha' }}
    </button>
</form>

@push('scripts')
<script>
(function () {
    let contador = {{ count($componentes) }};

    const contenedor = document.getElementById('componentes');
    const tipo = document.getElementById('tipo');
    const bloqueFormula = document.getElementById('bloqueFormula');

    // Los tipos sin fórmula no la piden
    const conFormula = @json(\App\Models\FichaTecnica::CON_FORMULA);

    function ajustar() {
        bloqueFormula.hidden = !conFormula.includes(tipo.value);
    }

    tipo.addEventListener('change', ajustar);
    ajustar();

    document.getElementById('anadirComponente').addEventListener('click', function () {
        const fila = document.createElement('div');
        fila.className = 'componente';

        fila.innerHTML =
            '<input type="text" name="formula[' + contador + '][marca]" placeholder="Marca">' +
            '<input type="text" name="formula[' + contador + '][tono]" placeholder="Tono">' +
            '<input type="number" name="formula[' + contador + '][cantidad]" placeholder="Cant." step="0.1" min="0">' +
            '<select name="formula[' + contador + '][unidad]">' +
                '<option value="g">g</option><option value="ml">ml</option><option value="cm">cm</option>' +
            '</select>' +
            '<button type="button" class="componente__quitar" data-quitar>&times;</button>';

        contenedor.appendChild(fila);
        enganchar(fila);
        contador++;
    });

    function enganchar(fila) {
        fila.querySelector('[data-quitar]')?.addEventListener('click', () => fila.remove());
    }

    document.querySelectorAll('.componente').forEach(enganchar);
})();
</script>
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}?v=16">
@endpush

@endsection
