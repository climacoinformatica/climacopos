@extends('panel.app')

@section('titulo', $cliente->exists ? 'Editar cliente' : 'Nuevo cliente')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $cliente->exists ? 'Editar ficha' : 'Nuevo cliente' }}</h1>
        @if ($cliente->exists)
            <p>{{ $cliente->nombreCompleto() }}</p>
        @endif
    </div>
    <a href="{{ $cliente->exists ? route('panel.clientes.ver', $cliente) : route('panel.clientes') }}"
       class="boton boton--secundario">Volver</a>
</div>

<form method="POST"
      action="{{ $cliente->exists
                 ? route('panel.clientes.guardar.editar', $cliente)
                 : route('panel.clientes.guardar') }}">
    @csrf

    <div class="tarjeta" style="max-width:760px">
        <h2>Datos de contacto</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="80"
                       value="{{ old('nombre', $cliente->nombre) }}">
            </div>

            <div class="campo">
                <label for="apellidos">Apellidos</label>
                <input type="text" id="apellidos" name="apellidos" maxlength="120"
                       value="{{ old('apellidos', $cliente->apellidos) }}">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="30"
                       value="{{ old('telefono', $cliente->telefono) }}">
            </div>

            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="160"
                       value="{{ old('email', $cliente->email) }}">
                <p class="campo__pista">Necesario para recordatorios y confirmaciones.</p>
            </div>

            <div class="campo">
                <label for="fecha_nac">Fecha de nacimiento</label>
                <input type="date" id="fecha_nac" name="fecha_nac"
                       value="{{ old('fecha_nac', $cliente->fecha_nac?->toDateString()) }}">
                <p class="campo__pista">Para felicitar el cumpleaños.</p>
            </div>

            <div class="campo">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" maxlength="200"
                       value="{{ old('direccion', $cliente->direccion) }}">
            </div>
        </div>
    </div>

    <div class="tarjeta" style="max-width:760px">
        <h2>Ficha profesional</h2>

        <div class="campo">
            <label for="avisos_ficha">Aviso importante</label>
            <input type="text" id="avisos_ficha" name="avisos_ficha" maxlength="300"
                   value="{{ old('avisos_ficha', $cliente->avisos_ficha) }}"
                   placeholder="No usar amoniaco">
            <p class="campo__pista">
                Sale en rojo en el TPV, la agenda y la ficha. Aquí va lo que hay
                que saber <strong>antes de tocar a la clienta</strong>.
            </p>
        </div>

        <div class="campo">
            <label for="alergias">Alergias y reacciones</label>
            <textarea id="alergias" name="alergias" rows="2">{{ old('alergias', $cliente->alergias) }}</textarea>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="tipo_cabello">Tipo de cabello</label>
                <input type="text" id="tipo_cabello" name="tipo_cabello" maxlength="60"
                       value="{{ old('tipo_cabello', $cliente->tipo_cabello) }}"
                       placeholder="Fino, poroso, teñido">
            </div>

            <div class="campo">
                <label for="profesional_habitual_id">Profesional habitual</label>
                <select id="profesional_habitual_id" name="profesional_habitual_id">
                    <option value="">— Sin preferencia —</option>
                    @foreach ($profesionales as $profesional)
                        <option value="{{ $profesional->id }}"
                                @selected(old('profesional_habitual_id', $cliente->profesional_habitual_id) == $profesional->id)>
                            {{ $profesional->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="campo">
            <label for="preferencias">Preferencias</label>
            <textarea id="preferencias" name="preferencias" rows="2"
                      placeholder="Café con leche, prefiere que no le hablen mientras trabaja">{{ old('preferencias', $cliente->preferencias) }}</textarea>
            <p class="campo__pista">
                Detalles que hacen que se sienta reconocida. Es lo que
                distingue a un salón al que se vuelve.
            </p>
        </div>

        <div class="campo">
            <label for="notas">Notas internas</label>
            <textarea id="notas" name="notas" rows="3">{{ old('notas', $cliente->notas) }}</textarea>
        </div>
    </div>

    <div class="tarjeta" style="max-width:760px">
        <h2>Permisos</h2>

        <div class="casilla">
            <input type="checkbox" id="acepta_marketing" name="acepta_marketing" value="1"
                   @checked(old('acepta_marketing', $cliente->acepta_marketing ?? false))>
            <div>
                <label for="acepta_marketing">Acepta comunicaciones comerciales</label>
                <small>
                    Sin esta casilla no se le pueden enviar promociones.
                    Los recordatorios de cita sí, porque son parte del servicio.
                </small>
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="bloqueado" name="bloqueado" value="1"
                   @checked(old('bloqueado', $cliente->bloqueado ?? false))>
            <div>
                <label for="bloqueado">Bloqueado</label>
                <small>No podrá reservar online ni asignarse a tickets.</small>
            </div>
        </div>
    </div>

    <button type="submit" class="boton">
        {{ $cliente->exists ? 'Guardar cambios' : 'Crear cliente' }}
    </button>
</form>

@endsection
