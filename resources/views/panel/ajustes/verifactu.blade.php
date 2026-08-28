@extends('panel.app')

@section('titulo', 'VERI*FACTU')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>VERI*FACTU</h1>
        <p>Registro de facturación ante la Agencia Tributaria</p>
    </div>
    @if ($estado['activo'])
        <span class="etiqueta" style="background:rgba(16,185,129,.18);color:#6ee7b7;font-size:.8rem;padding:.3rem .8rem">
            Activo
        </span>
    @endif
</div>

@unless ($estado['activo'])
    <div class="guia">
        <h2>Qué es y cuándo te afecta</h2>
        <p style="font-size:.87rem;line-height:1.7;color:var(--suave);margin-bottom:.75rem">
            Cada factura que emitas genera un registro con una huella digital encadenada
            a la anterior. Eso hace imposible borrar o modificar una venta sin que se note,
            y es lo que la Agencia Tributaria exige para evitar el software de doble uso.
        </p>
        <p style="font-size:.87rem;line-height:1.7;color:var(--suave)">
            <strong>Plazos:</strong> 1 de enero de 2027 para sociedades y 1 de julio de 2027
            para autónomos. Puedes activarlo antes de forma voluntaria: hacerlo con tiempo
            evita sustos y permite comprobar que todo funciona sin prisas.
        </p>
    </div>
@endunless

{{-- ---------- Activación ---------- --}}
<div class="tarjeta" style="max-width:760px">
    <h2>Estado</h2>

    @if (blank($empresa->nif))
        <p class="aviso aviso--error">
            Falta el NIF de la empresa. Sin NIF no se puede emitir ningún registro.
            <a href="{{ route('panel.ajustes') }}" class="enlace">Rellenarlo en Ajustes</a>
        </p>
    @endif

    <form method="POST" action="{{ route('panel.verifactu.activar') }}"
          onsubmit="return {{ $estado['activo'] && $estado['total'] > 0 ? 'confirm(\'Desactivar deja de declarar las facturas siguientes. ¿Seguro?\')' : 'true' }}">
        @csrf
        <input type="hidden" name="activo" value="{{ $estado['activo'] ? 0 : 1 }}">

        @if ($estado['activo'] && $estado['total'] > 0)
            <div class="casilla">
                <input type="checkbox" id="confirmar" name="confirmar" value="1">
                <div>
                    <label for="confirmar">Entiendo que dejaré de declarar mis facturas</label>
                    <small>Ya hay {{ $estado['total'] }} registro(s) emitido(s).</small>
                </div>
            </div>
        @endif

        <button type="submit" class="boton {{ $estado['activo'] ? 'boton--peligro' : '' }}"
                @disabled(blank($empresa->nif))>
            {{ $estado['activo'] ? 'Desactivar' : 'Activar VERI*FACTU' }}
        </button>
    </form>
</div>

@if ($estado['activo'])
    {{-- ---------- Cifras ---------- --}}
    <div class="cifras">
        <div class="cifra cifra--principal">
            <span>Registros emitidos</span>
            <strong>{{ $estado['total'] }}</strong>
        </div>
        <div class="cifra">
            <span>Aceptados</span>
            <strong>{{ $estado['aceptados'] }}</strong>
        </div>
        <div class="cifra {{ $estado['pendientes'] > 0 ? 'cifra--alerta' : '' }}">
            <span>Pendientes de enviar</span>
            <strong>{{ $estado['pendientes'] }}</strong>
        </div>
        <div class="cifra {{ $estado['rechazados'] > 0 ? 'cifra--alerta' : '' }}">
            <span>Rechazados</span>
            <strong>{{ $estado['rechazados'] }}</strong>
        </div>
    </div>

    {{-- ---------- Certificado ---------- --}}
    <div class="tarjeta" style="max-width:760px">
        <h2>Certificado digital</h2>
        <p class="tarjeta__ayuda">
            Hace falta para enviar a la Agencia Tributaria. Sirve el mismo certificado
            de representante con el que entras a la sede electrónica.
            Se guarda cifrado y fuera de internet: no es accesible por ninguna dirección web.
        </p>

        @if ($empresa->certificado_ruta)
            <p class="aviso aviso--ok">Certificado cargado.</p>

            <form method="POST" action="{{ route('panel.verifactu.certificado.borrar') }}"
                  onsubmit="return confirm('¿Eliminar el certificado? Dejarán de enviarse los registros.')">
                @csrf
                <button type="submit" class="boton boton--secundario boton--pequeno">Eliminar</button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.verifactu.certificado') }}" enctype="multipart/form-data">
                @csrf
                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="certificado">Fichero .p12 o .pfx</label>
                        <input type="file" id="certificado" name="certificado" accept=".p12,.pfx,.pem" required>
                    </div>
                    <div class="campo">
                        <label for="clave">Contraseña del certificado</label>
                        <input type="password" id="clave" name="clave" required autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="boton boton--pequeno">Subir certificado</button>
            </form>
        @endif
    </div>

    {{-- ---------- Integridad ---------- --}}
    <div class="tarjeta" style="max-width:760px">
        <h2>Integridad de la cadena</h2>
        <p class="tarjeta__ayuda">
            Recalcula todas las huellas y comprueba que nadie ha alterado ningún registro.
            Es lo que la Agencia comprobaría en una inspección.
        </p>

        @if ($cadena)
            @if ($cadena['integra'])
                <p class="aviso aviso--ok">
                    Cadena correcta. {{ $cadena['revisados'] }} registro(s) verificado(s).
                </p>
            @else
                <p class="aviso aviso--error">
                    <strong>Cadena rota</strong> en el registro {{ $cadena['roto_en'] }}.
                    Alguien ha modificado la base de datos directamente. Ponte en contacto
                    con soporte antes de seguir facturando.
                </p>
            @endif
        @endif

        <a href="{{ route('panel.verifactu', ['verificar' => 1]) }}" class="boton boton--secundario">
            Verificar ahora
        </a>
    </div>

    {{-- ---------- Registros ---------- --}}
    <div class="tarjeta">
        <div class="tarjeta__cabecera">
            <h2>Libro de registros</h2>
            <form method="POST" action="{{ route('panel.verifactu.enviar') }}">
                @csrf
                <button type="submit" class="boton boton--secundario boton--pequeno">
                    Enviar pendientes
                </button>
            </form>
        </div>

        @if ($registros->isEmpty())
            <p class="campo__pista">Todavía no hay registros. Se generan al cobrar cada ticket.</p>
        @else
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Factura</th><th>Tipo</th><th>Fecha</th>
                            <th class="num">Total</th><th>Huella</th><th>Estado</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($registros as $registro)
                        <tr>
                            <td><strong>{{ $registro->serie_numero }}</strong></td>
                            <td>{{ $registro->tipo === 'ALTA' ? 'Alta' : 'Anulación' }}</td>
                            <td>{{ $registro->fecha_expedicion->format('d/m/Y') }}</td>
                            <td class="num">{{ number_format($registro->total, 2, ',', '.') }} €</td>
                            <td>
                                <code style="font-size:.68rem">{{ substr($registro->huella, 0, 12) }}…</code>
                            </td>
                            <td>
                                <span class="etiqueta {{ in_array($registro->estado, ['RECHAZADO','ERROR_ENVIO']) ? 'etiqueta--inactivo' : '' }}">
                                    {{ $registro->etiqueta() }}
                                </span>
                                @if ($registro->mensaje_error)
                                    <div style="color:var(--error);font-size:.7rem">
                                        {{ $registro->mensaje_error }}
                                    </div>
                                @endif
                            </td>
                            <td style="display:flex;gap:.3rem">
                                <a href="{{ route('panel.verifactu.xml', $registro) }}" target="_blank"
                                   class="boton boton--secundario boton--pequeno">XML</a>

                                @unless ($registro->estaEnviado())
                                    <form method="POST" action="{{ route('panel.verifactu.reintentar', $registro) }}">
                                        @csrf
                                        <button type="submit" class="boton boton--secundario boton--pequeno">
                                            Enviar
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{ $registros->links() }}
        @endif
    </div>
@endif

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/informes.css') }}?v=10">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=10">
@endpush

@endsection
