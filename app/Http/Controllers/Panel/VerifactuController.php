<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\VerifactuRegistro;
use App\Services\Verifactu\EnvioAeat;
use App\Services\Verifactu\GestorVerifactu;
use App\Services\Verifactu\HuellaVerifactu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class VerifactuController extends Controller
{
    public function index(Request $peticion)
    {
        return view('panel.ajustes.verifactu', [
            'empresa'   => tenant(),
            'estado'    => (new GestorVerifactu())->estado(),
            'registros' => VerifactuRegistro::with('ticket')
                            ->orderByDesc('id')->paginate(30),
            'cadena'    => $peticion->boolean('verificar')
                            ? HuellaVerifactu::verificarCadena() : null,
        ]);
    }

    public function activar(Request $peticion)
    {
        $empresa = tenant();

        if (blank($empresa->nif)) {
            return back()->with('error',
                'Antes hay que rellenar el NIF de la empresa en Ajustes.');
        }

        $activar = $peticion->boolean('activo');

        /**
         * Desactivar con registros ya emitidos no es reversible sin dejar
         * un hueco: la cadena quedaria interrumpida y esos tickets no se
         * habrian declarado. Se avisa con claridad.
         */
        if (! $activar && VerifactuRegistro::exists()) {
            $peticion->validate(['confirmar' => ['required', 'accepted']]);
        }

        $empresa->forceFill(['verifactu_activo' => $activar])->save();

        Auditoria::registrar($activar ? 'verifactu_activado' : 'verifactu_desactivado',
            'empresas', $empresa->id);

        return back()->with('exito', $activar
            ? 'VERI*FACTU activado. A partir de ahora cada ticket genera su registro.'
            : 'VERI*FACTU desactivado.');
    }

    public function subirCertificado(Request $peticion)
    {
        $peticion->validate([
            'certificado' => ['required', 'file', 'max:512'],
            'clave'       => ['required', 'string', 'max:200'],
        ]);

        $fichero = $peticion->file('certificado');

        if (! in_array(strtolower($fichero->getClientOriginalExtension()), ['p12', 'pfx', 'pem'])) {
            return back()->with('error', 'El certificado debe ser un fichero .p12, .pfx o .pem.');
        }

        // Fuera del directorio publico: nunca accesible por URL
        $ruta = $fichero->storeAs(
            'certificados',
            'cert_' . tenant()->id . '_' . uniqid() . '.' . $fichero->getClientOriginalExtension(),
            'local',
        );

        tenant()->forceFill([
            'certificado_ruta'  => $ruta,
            'certificado_clave' => Crypt::encryptString($peticion->input('clave')),
        ])->save();

        Auditoria::registrar('verifactu_certificado_subido', 'empresas', tenant()->id);

        return back()->with('exito', 'Certificado guardado.');
    }

    public function borrarCertificado()
    {
        $empresa = tenant();

        if ($empresa->certificado_ruta) {
            Storage::disk('local')->delete($empresa->certificado_ruta);
        }

        $empresa->forceFill([
            'certificado_ruta'  => null,
            'certificado_clave' => null,
        ])->save();

        return back()->with('exito', 'Certificado eliminado.');
    }

    public function enviarPendientes()
    {
        $resultado = (new EnvioAeat())->enviarPendientes();

        return back()->with('exito',
            "{$resultado['enviados']} registro(s) enviado(s), {$resultado['fallidos']} con error.");
    }

    public function reintentar(VerifactuRegistro $registro)
    {
        $ok = (new EnvioAeat())->enviar($registro);

        return back()->with($ok ? 'exito' : 'error',
            $ok ? 'Registro aceptado por la AEAT.'
                : 'No se pudo enviar: ' . ($registro->fresh()->mensaje_error ?? 'error desconocido'));
    }

    public function verXml(VerifactuRegistro $registro)
    {
        $xml = $registro->xml ?: (new \App\Services\Verifactu\GeneradorXml())->registro($registro);

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $registro->serie_numero . '.xml"',
        ]);
    }
}
