@extends('panel.app')

@section('titulo', 'Usuarios')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Usuarios</h1>
        <p>
            {{ $limites['profesionales'] }} profesional(es)
            @if ($limites['profesionales_max'])
                de {{ $limites['profesionales_max'] }} que permite tu plan
            @endif
        </p>
    </div>
    <a href="{{ route('panel.usuarios.crear') }}" class="boton">Nuevo usuario</a>
</div>

{{-- Las credenciales solo se muestran una vez --}}
@if (session('credenciales'))
    @php $c = session('credenciales'); @endphp
    <div class="tarjeta" style="border-color: var(--ok)">
        <h2>Credenciales de {{ $c['nombre'] }}</h2>
        <p class="tarjeta__ayuda">
            <strong>Anótalas ahora: no se vuelven a mostrar.</strong>
            Se guardan cifradas, así que si se pierden hay que generar otras.
        </p>

        <div class="rejilla-campos">
            @if (! empty($c['pin']))
                <div class="campo">
                    <label>PIN de acceso al TPV</label>
                    <input type="text" readonly value="{{ $c['pin'] }}"
                           style="font-family:monospace;font-size:1.4rem;text-align:center;letter-spacing:4px">
                </div>
            @endif

            @if (! empty($c['password']))
                <div class="campo">
                    <label>Contraseña</label>
                    <input type="text" readonly value="{{ $c['password'] }}"
                           style="font-family:monospace">
                    <p class="campo__pista">Para acciones sensibles y acceso desde fuera.</p>
                </div>
            @endif
        </div>
    </div>
@endif

@if ($limites['profesionales_max'] && $limites['profesionales'] >= $limites['profesionales_max'])
    <p class="aviso aviso--pendiente">
        Has alcanzado el límite de profesionales de tu plan
        {{ $limites['plan'] ? '«' . $limites['plan'] . '»' : '' }}.
        Para añadir más, cámbialo desde
        <a href="{{ route('panel.suscripcion') }}" class="enlace">Suscripción</a>.
    </p>
@endif

<div class="tarjeta" style="padding:.5rem">
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th><th>Perfil</th><th>Tipo</th>
                    <th>Comisión</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($usuarios as $usuario)
                <tr @class(['fila-anulada' => $usuario->trashed() || $usuario->estado !== 'ACTIVO'])>
                    <td>
                        <span class="punto-color" style="background:{{ $usuario->color_agenda }}"></span>
                        <strong>{{ $usuario->nombre }}</strong>
                        @if ($usuario->alias && $usuario->alias !== $usuario->nombre)
                            <span style="color:var(--suave)">({{ $usuario->alias }})</span>
                        @endif
                        @if ($usuario->email)
                            <div style="color:var(--suave);font-size:.72rem">{{ $usuario->email }}</div>
                        @endif
                    </td>
                    <td>{{ $usuario->perfil?->nombre }}</td>
                    <td>
                        @if ($usuario->es_profesional)
                            <span class="etiqueta">Profesional</span>
                        @endif
                        @if ($usuario->en_formacion)
                            <span class="etiqueta" style="background:var(--aviso);color:#422006">Formación</span>
                        @endif
                        @if ($usuario->ficha_jornada)
                            <span class="etiqueta">Ficha</span>
                        @endif
                    </td>
                    <td>
                        {{ $usuario->comision_pct > 0
                           ? rtrim(rtrim(number_format($usuario->comision_pct, 2, ',', ''), '0'), ',') . '%'
                           : '—' }}
                    </td>
                    <td>
                        @if ($usuario->trashed())
                            <span class="etiqueta etiqueta--inactivo">De baja</span>
                        @elseif ($usuario->bloqueado_hasta && $usuario->bloqueado_hasta->isFuture())
                            <span class="etiqueta etiqueta--inactivo">Bloqueado</span>
                        @else
                            <span class="etiqueta">Activo</span>
                        @endif
                    </td>
                    <td>
                        <div class="acciones-fila">
                            @if ($usuario->trashed())
                                <form method="POST" action="{{ route('panel.usuarios.reactivar', $usuario->id) }}">
                                    @csrf
                                    <button type="submit" class="boton boton--secundario boton--pequeno">
                                        Reactivar
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('panel.usuarios.editar', $usuario) }}"
                                   class="boton boton--secundario boton--pequeno">Editar</a>

                                <button type="button"
                                        class="boton boton--secundario boton--pequeno"
                                        onclick="abrirClaves({{ $usuario->id }}, '{{ addslashes($usuario->nombre) }}')">
                                    Claves
                                </button>

                                @if ($usuario->bloqueado_hasta && $usuario->bloqueado_hasta->isFuture())
                                    <form method="POST" action="{{ route('panel.usuarios.desbloquear', $usuario) }}">
                                        @csrf
                                        <button type="submit" class="boton boton--secundario boton--pequeno">
                                            Desbloquear
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('panel.usuarios.desactivar', $usuario) }}"
                                      onsubmit="return confirm('¿Dar de baja a {{ $usuario->nombre }}? Su historial y sus fichajes se conservan.')">
                                    @csrf
                                    <button type="submit" class="boton boton--secundario boton--pequeno">
                                        Dar de baja
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{--
    Dialogo de claves.

    Uno solo para toda la tabla, en lugar de uno por usuario: con quince
    empleados serian quince formularios ocultos en cada carga de pagina.
--}}
<div class="modal" id="modalClaves" hidden>
    <div class="modal__caja" style="max-width:460px">
        <h2 id="clavesNombre">Claves</h2>

        <p class="tarjeta__ayuda">
            Puedes escribir una que la persona recuerde, o dejarlo en blanco
            y que se genere sola.
        </p>

        {{-- ---------- PIN ---------- --}}
        <form method="POST" id="formPin" class="bloque-claves">
            @csrf

            <div class="campo">
                <label for="pinNuevo">PIN de acceso al TPV</label>
                <input type="text" id="pinNuevo" name="pin"
                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                       placeholder="En blanco = uno al azar"
                       style="font-family:monospace;font-size:1.3rem;text-align:center;letter-spacing:6px">
                <p class="campo__pista">
                    Cuatro dígitos. No puede coincidir con el de otra persona:
                    si dos comparten PIN, lo que teclee uno se le apunta al otro.
                </p>
            </div>

            <button type="submit" class="boton boton--pequeno">Cambiar el PIN</button>
        </form>

        <hr class="separador-claves">

        {{-- ---------- Contraseña ---------- --}}
        <form method="POST" id="formPassword" class="bloque-claves">
            @csrf

            <div class="campo">
                <label for="passwordNueva">Contraseña</label>
                <input type="text" id="passwordNueva" name="password"
                       maxlength="60" placeholder="En blanco = una al azar"
                       autocomplete="off">
                <p class="campo__pista">
                    Para acciones delicadas: anular tickets, ver informes.
                    Al menos seis caracteres.
                </p>
            </div>

            <button type="submit" class="boton boton--pequeno">Cambiar la contraseña</button>
        </form>

        <div class="modal__pie">
            <button type="button" class="boton boton--secundario" onclick="cerrarClaves()">
                Cerrar
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
const rutaPin = "{{ route('panel.usuarios.pin', ['usuario' => '__ID__']) }}";
const rutaPassword = "{{ route('panel.usuarios.password', ['usuario' => '__ID__']) }}";

function abrirClaves(id, nombre) {
    document.getElementById('clavesNombre').textContent = 'Claves de ' + nombre;

    document.getElementById('formPin').action = rutaPin.replace('__ID__', id);
    document.getElementById('formPassword').action = rutaPassword.replace('__ID__', id);

    document.getElementById('pinNuevo').value = '';
    document.getElementById('passwordNueva').value = '';

    document.getElementById('modalClaves').hidden = false;
}

function cerrarClaves() {
    document.getElementById('modalClaves').hidden = true;
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarClaves();
});
</script>
<link rel="stylesheet" href="{{ asset('css/fichajes.css') }}?v=32">
<link rel="stylesheet" href="{{ asset('css/claves.css') }}?v=32">
@endpush

@endsection
