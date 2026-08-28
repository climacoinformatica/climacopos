@extends('admin.base')

@section('titulo', 'Correo')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Correo saliente</h1>
        <p>El servidor desde el que salen los avisos a los clientes</p>
    </div>
</div>

@unless ($host)
    <div class="guia">
        <h2>Qué necesitas</h2>
        <p style="font-size:.87rem;line-height:1.7;color:var(--suave);margin-bottom:.75rem">
            Una cuenta de correo con acceso SMTP. Vale la de tu propio dominio,
            una de Gmail o un servicio de envío como Brevo.
        </p>
        <ol>
            <li>
                <strong>Si usas Gmail</strong>, no sirve tu contraseña normal: hay que crear
                una «contraseña de aplicación» desde la seguridad de tu cuenta de Google,
                con la verificación en dos pasos activada.
            </li>
            <li>
                <strong>Si envías mucho volumen</strong>, mejor un servicio de envío.
                Brevo da 300 correos al día gratis y evita que tu dominio acabe marcado
                como spam, que es lo que pasa cuando se envían cientos de correos desde
                una cuenta normal.
            </li>
            <li>
                <strong>El remitente debe ser una dirección real</strong> del mismo dominio
                que la cuenta. Poner una inventada hace que la mayoría de servidores
                rechacen el envío.
            </li>
        </ol>
    </div>
@endunless

<form method="POST" action="{{ route('admin.correo.guardar') }}">
    @csrf

    <div class="tarjeta" style="max-width:760px">
        <h2>Servidor SMTP</h2>

        <div class="campo">
            <label for="proveedor">Proveedor</label>
            <select id="proveedor">
                <option value="">— Elige para rellenar automáticamente —</option>
                @foreach ($proveedores as $clave => $datos)
                    <option value="{{ $clave }}"
                            data-host="{{ $datos['host'] }}"
                            data-puerto="{{ $datos['puerto'] }}"
                            data-cifrado="{{ $datos['cifrado'] }}"
                            data-nota="{{ $datos['nota'] }}">
                        {{ $datos['nombre'] }}
                    </option>
                @endforeach
            </select>
            <p class="campo__pista" id="notaProveedor"></p>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="correo_host">Servidor</label>
                <input type="text" id="correo_host" name="correo_host" required
                       value="{{ old('correo_host', $host) }}" placeholder="smtp.midominio.com">
            </div>

            <div class="campo">
                <label for="correo_puerto">Puerto</label>
                <input type="number" id="correo_puerto" name="correo_puerto" required
                       value="{{ old('correo_puerto', $puerto) }}">
                <p class="campo__pista">587 con TLS, 465 con SSL.</p>
            </div>

            <div class="campo">
                <label for="correo_cifrado">Cifrado</label>
                <select id="correo_cifrado" name="correo_cifrado" required>
                    <option value="tls"     @selected($cifrado === 'tls')>TLS (recomendado)</option>
                    <option value="ssl"     @selected($cifrado === 'ssl')>SSL</option>
                    <option value="ninguno" @selected($cifrado === 'ninguno')>Sin cifrado</option>
                </select>
            </div>

            <div class="campo">
                <label for="correo_usuario">Usuario</label>
                <input type="text" id="correo_usuario" name="correo_usuario"
                       value="{{ old('correo_usuario', $usuario) }}"
                       placeholder="avisos@midominio.com" autocomplete="off">
            </div>

            <div class="campo">
                <label for="correo_password">
                    Contraseña
                    @if ($tienePass)
                        <span class="marca-guardada">guardada</span>
                    @endif
                </label>
                <input type="password" id="correo_password" name="correo_password"
                       placeholder="{{ $tienePass ? 'Déjalo vacío para no cambiarla' : '' }}"
                       autocomplete="new-password">
            </div>
        </div>

        <h3 style="font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;
                   color:var(--suave);margin:1.5rem 0 .75rem;padding-top:1rem;
                   border-top:1px solid var(--panel2)">
            Remitente
        </h3>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="correo_remitente">Dirección</label>
                <input type="email" id="correo_remitente" name="correo_remitente" required
                       value="{{ old('correo_remitente', $remitente) }}"
                       placeholder="no-responder@climacopos.com">
                <p class="campo__pista">
                    Debe ser una dirección real del mismo dominio que la cuenta.
                </p>
            </div>

            <div class="campo">
                <label for="correo_nombre">Nombre por defecto</label>
                <input type="text" id="correo_nombre" name="correo_nombre" required
                       value="{{ old('correo_nombre', $nombre) }}">
                <p class="campo__pista">
                    Solo se usa fuera del contexto de un salón. En los avisos a clientas
                    aparece el nombre de su peluquería, que es lo que ellas reconocen.
                </p>
            </div>
        </div>

        <div class="casilla" style="margin-top:1rem">
            <input type="checkbox" id="correo_verificar_certificado" name="correo_verificar_certificado"
                   value="1" @checked($verificar)>
            <div>
                <label for="correo_verificar_certificado">Verificar el certificado del servidor</label>
                <small>
                    Déjalo marcado. Solo desactívalo si tu servidor usa un certificado
                    autofirmado y la conexión falla por ese motivo.
                </small>
            </div>
        </div>

        <button type="submit" class="boton" style="margin-top:1.25rem">Guardar</button>
    </div>
</form>

<div class="tarjeta" style="max-width:760px">
    <h2>Enviar un correo de prueba</h2>
    <p class="tarjeta__ayuda">
        Guarda primero la configuración. Si no llega en un minuto, mira también
        la carpeta de spam: es lo primero que suele pasar con un dominio nuevo.
    </p>

    <form method="POST" action="{{ route('admin.correo.probar') }}">
        @csrf
        <div class="campo">
            <label for="destino">Enviar a</label>
            <input type="email" id="destino" name="destino" required
                   value="{{ $superadmin->email }}">
        </div>
        <button type="submit" class="boton boton--secundario" @disabled(blank($host))>
            Enviar prueba
        </button>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('proveedor').addEventListener('change', function () {
    const opcion = this.options[this.selectedIndex];
    const host = opcion.dataset.host;

    if (host) {
        document.getElementById('correo_host').value = host;
        document.getElementById('correo_puerto').value = opcion.dataset.puerto;
        document.getElementById('correo_cifrado').value = opcion.dataset.cifrado;
    }

    document.getElementById('notaProveedor').textContent = opcion.dataset.nota || '';
});
</script>
@endpush

@endsection
