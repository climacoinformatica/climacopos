<div class="fila-diseno">
    <input type="text" name="{{ $grupo }}[{{ $i }}][texto]"
           placeholder="Texto de la línea"
           value="{{ $fila['texto'] ?? '' }}">

    <select name="{{ $grupo }}[{{ $i }}][alineacion]">
        <option value="IZQUIERDA" @selected(($fila['alineacion'] ?? 'CENTRO') === 'IZQUIERDA')>Izquierda</option>
        <option value="CENTRO"    @selected(($fila['alineacion'] ?? 'CENTRO') === 'CENTRO')>Centro</option>
        <option value="DERECHA"   @selected(($fila['alineacion'] ?? 'CENTRO') === 'DERECHA')>Derecha</option>
    </select>

    <div class="fila-diseno__estilos">
        <label title="Negrita">
            <input type="checkbox" name="{{ $grupo }}[{{ $i }}][negrita]" value="1"
                   @checked(! empty($fila['negrita']))> N
        </label>
        <label title="Doble alto">
            <input type="checkbox" name="{{ $grupo }}[{{ $i }}][doble_alto]" value="1"
                   @checked(! empty($fila['doble_alto']))> A
        </label>
        <label title="Doble ancho">
            <input type="checkbox" name="{{ $grupo }}[{{ $i }}][doble_ancho]" value="1"
                   @checked(! empty($fila['doble_ancho']))> W
        </label>
    </div>

    <button type="button" class="fila-diseno__quitar" data-quitar>&times;</button>
</div>
