@extends('panel.base')

@section('titulo', 'Selecciona tu usuario')

@section('contenido')
<div class="selector">
    <header class="selector__cabecera">
        @if ($empresa->logo)
            <img src="{{ tenant_asset($empresa->logo) }}" alt="" class="selector__logo">
        @endif
        <h1>{{ $empresa->nombre_comercial }}</h1>
        <p class="selector__terminal">{{ $terminal?->nombre ?? 'Terminal sin nombre' }}</p>
    </header>

    @if (session('exito'))
        <p class="aviso aviso--ok">{{ session('exito') }}</p>
    @endif

    @if ($usuarios->isEmpty())
        <p class="aviso aviso--error">
            No hay usuarios activos. Crea el primero con:<br>
            <code>php artisan climacopos:crear-usuario {{ $empresa->slug }} "Tu nombre"</code>
        </p>
    @endif

    <ul class="rejilla">
        @foreach ($usuarios as $usuario)
            <li>
                <button type="button"
                        class="tarjeta {{ $usuario->en_formacion ? 'tarjeta--formacion' : '' }}"
                        data-usuario="{{ $usuario->id }}"
                        data-nombre="{{ $usuario->nombre }}">
                    <span class="tarjeta__avatar" style="--color: {{ $usuario->color_agenda }}">
                        @if ($usuario->foto)
                            <img src="{{ tenant_asset($usuario->foto) }}" alt="">
                        @else
                            {{ $usuario->iniciales() }}
                        @endif
                    </span>
                    <span class="tarjeta__nombre">{{ $usuario->alias ?: $usuario->nombre }}</span>
                    <span class="tarjeta__perfil">{{ $usuario->perfil->nombre }}</span>
                    @if ($usuario->en_formacion)
                        <span class="tarjeta__etiqueta">FORMACIÓN</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
</div>

<div class="modal" id="modalPin" hidden>
    <div class="modal__caja">
        <button type="button" class="modal__cerrar" id="cerrarPin">&times;</button>
        <h2 id="pinNombre"></h2>
        <p class="modal__ayuda">Introduce tu PIN</p>

        <div class="puntos" id="puntos"></div>

        @if ($errors->any())
            <p class="aviso aviso--error">{{ $errors->first() }}</p>
        @endif
        <p class="aviso aviso--error" id="pinError" hidden></p>

        <div class="teclado">
            @foreach ([1,2,3,4,5,6,7,8,9] as $n)
                <button type="button" class="tecla" data-digito="{{ $n }}">{{ $n }}</button>
            @endforeach
            <button type="button" class="tecla tecla--aux" id="borrarPin">&larr;</button>
            <button type="button" class="tecla" data-digito="0">0</button>
            <button type="button" class="tecla tecla--ok" id="aceptarPin">&crarr;</button>
        </div>

        <form method="POST" action="{{ route('panel.selector.entrar') }}" id="formPin">
            @csrf
            <input type="hidden" name="usuario_id" id="campoUsuario">
            <input type="hidden" name="pin" id="campoPin">
        </form>
    </div>
</div>

<script>
(function () {
    const modal    = document.getElementById('modalPin');
    const puntos   = document.getElementById('puntos');
    const form     = document.getElementById('formPin');
    const campoPin = document.getElementById('campoPin');
    const campoUsr = document.getElementById('campoUsuario');
    const error    = document.getElementById('pinError');
    const LONGITUD_MAX = 8;
    const LONGITUD_MIN = 4;

    let pin = '';

    function pintar() {
        puntos.innerHTML = '';
        for (let i = 0; i < LONGITUD_MAX; i++) {
            const p = document.createElement('span');
            p.className = 'punto' + (i < pin.length ? ' punto--lleno' : '');
            puntos.appendChild(p);
        }
    }

    function abrir(id, nombre) {
        pin = '';
        campoUsr.value = id;
        document.getElementById('pinNombre').textContent = nombre;
        error.hidden = true;
        modal.hidden = false;
        pintar();
    }

    function cerrar() {
        modal.hidden = true;
        pin = '';
    }

    function enviar() {
        if (pin.length < LONGITUD_MIN) {
            error.textContent = 'El PIN tiene al menos ' + LONGITUD_MIN + ' dígitos.';
            error.hidden = false;
            return;
        }
        campoPin.value = pin;
        form.submit();
    }

    document.querySelectorAll('.tarjeta').forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrir(this.dataset.usuario, this.dataset.nombre);
        });
    });

    document.querySelectorAll('[data-digito]').forEach(function (tecla) {
        tecla.addEventListener('click', function () {
            if (pin.length < LONGITUD_MAX) {
                pin += this.dataset.digito;
                error.hidden = true;
                pintar();
            }
        });
    });

    document.getElementById('borrarPin').addEventListener('click', function () {
        pin = pin.slice(0, -1);
        pintar();
    });

    document.getElementById('aceptarPin').addEventListener('click', enviar);
    document.getElementById('cerrarPin').addEventListener('click', cerrar);

    document.addEventListener('keydown', function (e) {
        if (modal.hidden) return;
        if (e.key >= '0' && e.key <= '9' && pin.length < LONGITUD_MAX) {
            pin += e.key; pintar(); error.hidden = true;
        } else if (e.key === 'Backspace') {
            pin = pin.slice(0, -1); pintar();
        } else if (e.key === 'Enter') {
            enviar();
        } else if (e.key === 'Escape') {
            cerrar();
        }
    });

    @if ($errors->any())
        const ultimo = @json(old('usuario_id'));
        if (ultimo) {
            const boton = document.querySelector('[data-usuario="' + ultimo + '"]');
            if (boton) abrir(ultimo, boton.dataset.nombre);
        }
    @endif
})();
</script>
@endsection
