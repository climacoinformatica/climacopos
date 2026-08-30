@extends('panel.app')

@section('titulo', $familia->exists ? $familia->nombre : 'Nueva familia')

@section('contenido')

<div class="titulo-pagina">
    <h1>{{ $familia->exists ? 'Editar familia' : 'Nueva familia' }}</h1>
    <a href="{{ route('panel.catalogo.familias') }}" class="boton boton--secundario">Volver</a>
</div>

<form method="POST"
      {{--
          OJO CON EL NOMBRE DE LA RUTA

          Al editar hay que usar `familias.guardar.editar`, que es la que
          lleva {familia} en la URL. Con `familias.guardar` a secas, el
          id se colaba como cadena de consulta (?familia=5), la peticion
          entraba por la ruta de CREAR y cada edicion generaba una
          familia duplicada en vez de actualizar la existente.
      --}}
      action="{{ $familia->exists
                 ? route('panel.catalogo.familias.guardar.editar', $familia)
                 : route('panel.catalogo.familias.guardar') }}"
      enctype="multipart/form-data">
    @csrf

    <div class="tarjeta" style="max-width:640px">
        <div class="campo">
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" required
                   value="{{ old('nombre', $familia->nombre) }}">
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="tipo">Contiene *</label>
                <select id="tipo" name="tipo" required>
                    <option value="SERVICIO" @selected(old('tipo', $familia->tipo) === 'SERVICIO')>Servicios</option>
                    <option value="PRODUCTO" @selected(old('tipo', $familia->tipo) === 'PRODUCTO')>Productos</option>
                    <option value="AMBOS"    @selected(old('tipo', $familia->tipo) === 'AMBOS')>Ambos</option>
                </select>
            </div>

            <div class="campo">
                <label for="familia_padre_id">Familia superior</label>
                <select id="familia_padre_id" name="familia_padre_id">
                    <option value="">— Ninguna (familia principal) —</option>
                    @foreach ($padres as $padre)
                        <option value="{{ $padre->id }}" @selected(old('familia_padre_id', $familia->familia_padre_id) == $padre->id)>
                            {{ $padre->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="0" max="999"
                       value="{{ old('orden', $familia->orden ?? 0) }}">
            </div>

            <div class="campo">
                <label for="color">Color</label>
                <input type="color" id="color" name="color"
                       value="{{ old('color', $familia->color ?? '#6366f1') }}">
                <p class="campo__pista">Identifica la familia en el TPV y la agenda.</p>
            </div>
        </div>

        <div class="campo">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion">{{ old('descripcion', $familia->descripcion) }}</textarea>
        </div>

        <div class="campo">
            <label for="imagen">Imagen</label>

            {{--
                Se enseña la que hay, no solo un aviso de texto.

                Ver la miniatura evita el error tipico de subir otra
                creyendo que no habia ninguna, y de paso confirma que la
                anterior se guardo bien.
            --}}
            <div class="vista-imagen">
                @if ($url = $familia->urlImagen())
                    <img src="{{ $url }}" alt="Imagen de {{ $familia->nombre }}">
                @else
                    <span class="vista-imagen__vacio">Sin imagen</span>
                @endif
            </div>

            <input type="file" id="imagen" name="imagen" accept="image/*">

            @if ($familia->imagen)
                <p class="campo__pista">Sube otra para reemplazarla.</p>
            @endif
        </div>

        <div class="casilla">
            <input type="checkbox" id="visible_online" name="visible_online" value="1"
                   @checked(old('visible_online', $familia->visible_online ?? true))>
            <div>
                <label for="visible_online">Visible en el portal de reservas</label>
                <small>Las familias de productos suelen dejarse ocultas.</small>
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="activa" name="activa" value="1"
                   @checked(old('activa', $familia->activa ?? true))>
            <label for="activa">Activa</label>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <button type="submit" class="boton">{{ $familia->exists ? 'Guardar cambios' : 'Crear familia' }}</button>
        <a href="{{ route('panel.catalogo.familias') }}" class="boton boton--secundario">Cancelar</a>
    </div>
</form>

@if ($familia->exists)
    <form method="POST" action="{{ route('panel.catalogo.familias.borrar', $familia) }}"
          style="margin-top:2rem" onsubmit="return confirm('¿Borrar esta familia?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="boton boton--secundario boton--pequeno">Borrar familia</button>
    </form>
@endif

@endsection
