@extends('panel.app')

@section('titulo', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>{{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
        @if ($usuario->exists)
            <p>{{ $usuario->nombre }}</p>
        @endif
    </div>
    <a href="{{ route('panel.usuarios') }}" class="boton boton--secundario">Volver</a>
</div>

<form method="POST"
      action="{{ $usuario->exists
                 ? route('panel.usuarios.guardar.editar', $usuario)
                 : route('panel.usuarios.guardar') }}">
    @csrf

    <div class="tarjeta" style="max-width:760px">
        <h2>Datos</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="80"
                       value="{{ old('nombre', $usuario->nombre) }}">
            </div>

            <div class="campo">
                <label for="alias">Alias</label>
                <input type="text" id="alias" name="alias" maxlength="30"
                       value="{{ old('alias', $usuario->alias) }}">
                <p class="campo__pista">Cómo aparece en la agenda y el ticket.</p>
            </div>

            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="160"
                       value="{{ old('email', $usuario->email) }}">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="30"
                       value="{{ old('telefono', $usuario->telefono) }}">
            </div>

            <div class="campo">
                <label for="nif">NIF</label>
                <input type="text" id="nif" name="nif" maxlength="20"
                       value="{{ old('nif', $usuario->nif) }}">
                <p class="campo__pista">Aparece en el registro de jornada.</p>
            </div>

            <div class="campo">
                <label for="color_agenda">Color en la agenda</label>
                <input type="color" id="color_agenda" name="color_agenda"
                       value="{{ old('color_agenda', $usuario->color_agenda ?? '#6366f1') }}">
            </div>
        </div>
    </div>

    <div class="tarjeta" style="max-width:760px">
        <h2>Permisos y trabajo</h2>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="perfil_id">Perfil *</label>
                <select id="perfil_id" name="perfil_id" required>
                    @foreach ($perfiles as $perfil)
                        <option value="{{ $perfil->id }}"
                                @selected(old('perfil_id', $usuario->perfil_id) == $perfil->id)>
                            {{ $perfil->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="campo__pista">Define qué puede ver y hacer.</p>
            </div>

            <div class="campo">
                <label for="comision_pct">Comisión (%)</label>
                <input type="number" id="comision_pct" name="comision_pct" step="0.01" min="0" max="100"
                       value="{{ old('comision_pct', $usuario->comision_pct ?? 0) }}">
                <p class="campo__pista">Se calcula sobre lo que ejecuta, no sobre lo que cobra.</p>
            </div>

            <div class="campo">
                <label for="horas_semana">Horas semanales</label>
                <input type="number" id="horas_semana" name="horas_semana" step="0.5" min="0" max="60"
                       value="{{ old('horas_semana', $usuario->horas_semana) }}">
            </div>

            <div class="campo">
                <label for="fecha_alta_lab">Fecha de alta</label>
                <input type="date" id="fecha_alta_lab" name="fecha_alta_lab"
                       value="{{ old('fecha_alta_lab', $usuario->fecha_alta_lab?->toDateString()) }}">
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="es_profesional" name="es_profesional" value="1"
                   @checked(old('es_profesional', $usuario->es_profesional ?? false))>
            <div>
                <label for="es_profesional">Atiende clientas</label>
                <small>
                    Aparece en la agenda y se le pueden asignar servicios.
                    @if ($limites['profesionales_max'])
                        Tu plan permite {{ $limites['profesionales_max'] }}.
                    @endif
                </small>
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="ficha_jornada" name="ficha_jornada" value="1"
                   @checked(old('ficha_jornada', $usuario->ficha_jornada ?? true))>
            <div>
                <label for="ficha_jornada">Registra su jornada</label>
                <small>
                    El registro horario es obligatorio para todo el personal contratado.
                    Desactívalo solo para el titular si no está en nómina.
                </small>
            </div>
        </div>

        <div class="casilla">
            <input type="checkbox" id="en_formacion" name="en_formacion" value="1"
                   @checked(old('en_formacion', $usuario->en_formacion ?? false))>
            <div>
                <label for="en_formacion">En formación</label>
                <small>
                    Sus documentos van en serie aparte, sin valor fiscal, y solo
                    podrá cobrar en efectivo.
                </small>
            </div>
        </div>
    </div>

    @unless ($usuario->exists)
        <div class="tarjeta" style="max-width:760px">
            <h2>Acceso</h2>
            <p class="tarjeta__ayuda">
                Si los dejas vacíos se generan solos y se muestran una vez al guardar.
            </p>

            <div class="rejilla-campos">
                <div class="campo">
                    <label for="pin">PIN (4 dígitos)</label>
                    <input type="text" id="pin" name="pin" maxlength="4" pattern="[0-9]{4}"
                           inputmode="numeric" placeholder="Se genera solo">
                </div>

                <div class="campo">
                    <label for="password">Contraseña</label>
                    <input type="text" id="password" name="password"
                           placeholder="Se genera sola" autocomplete="new-password">
                </div>
            </div>
        </div>
    @endunless

    <button type="submit" class="boton">
        {{ $usuario->exists ? 'Guardar cambios' : 'Crear usuario' }}
    </button>
</form>

@endsection
