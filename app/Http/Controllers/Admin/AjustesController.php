<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigPlataforma;
use App\Services\Pagos\PasarelaStripe;
use Illuminate\Http\Request;

/**
 * Ajustes de la plataforma.
 *
 * Todo lo que antes había que escribir en el .env se configura aquí:
 * el objetivo es que montar la plataforma no exija abrir un fichero
 * por SSH ni saber qué es una variable de entorno.
 */
class AjustesController extends Controller
{
    public function pagos()
    {
        return view('admin.ajustes-pagos', [
            'publica'      => ConfigPlataforma::obtener('stripe_publica', ''),
            'tieneSecreto' => ConfigPlataforma::tiene('stripe_secreto'),
            'tieneWebhook' => ConfigPlataforma::tiene('stripe_webhook'),
            'comision'     => ConfigPlataforma::obtener('comision_plataforma_pct', 0),
            'pasarela'     => ConfigPlataforma::obtener('pasarela', 'stripe'),
            'modo'         => str_starts_with((string) ConfigPlataforma::obtener('stripe_publica', ''), 'pk_live')
                              ? 'PRODUCCIÓN' : 'PRUEBAS',
        ]);
    }

    public function guardarPagos(Request $peticion)
    {
        $datos = $peticion->validate([
            'stripe_publica' => ['nullable', 'string', 'max:255', 'regex:/^pk_(test|live)_/'],
            'stripe_secreto' => ['nullable', 'string', 'max:255', 'regex:/^sk_(test|live)_/'],
            'stripe_webhook' => ['nullable', 'string', 'max:255', 'regex:/^whsec_/'],
            'comision_plataforma_pct' => ['required', 'numeric', 'min:0', 'max:50'],
        ], [
            'stripe_publica.regex' => 'La clave pública debe empezar por pk_test_ o pk_live_.',
            'stripe_secreto.regex' => 'La clave secreta debe empezar por sk_test_ o sk_live_.',
            'stripe_webhook.regex' => 'El secreto del webhook debe empezar por whsec_.',
        ]);

        // Los campos secretos vacíos NO borran lo que ya había:
        // se muestran enmascarados, así que un envío normal los deja vacíos.
        foreach (['stripe_publica', 'stripe_secreto', 'stripe_webhook'] as $clave) {
            if (filled($datos[$clave] ?? null)) {
                ConfigPlataforma::guardar($clave, $datos[$clave]);
            }
        }

        ConfigPlataforma::guardar('comision_plataforma_pct', $datos['comision_plataforma_pct']);

        // Aviso si se mezclan claves de prueba y de producción
        $aviso = null;
        $publica = ConfigPlataforma::obtener('stripe_publica', '');
        $secreto = ConfigPlataforma::obtener('stripe_secreto', '');

        if ($publica && $secreto) {
            $publicaLive = str_starts_with($publica, 'pk_live');
            $secretoLive = str_starts_with($secreto, 'sk_live');

            if ($publicaLive !== $secretoLive) {
                $aviso = 'Cuidado: has mezclado una clave de pruebas con otra de producción. '
                       . 'Los pagos fallarán hasta que las dos sean del mismo tipo.';
            }
        }

        return back()->with('exito', 'Ajustes guardados.')
                     ->with($aviso ? 'error' : 'nada', $aviso);
    }

    /** Comprueba las claves contra Stripe sin mover dinero. */
    public function probarPagos()
    {
        if (! ConfigPlataforma::tiene('stripe_secreto')) {
            return back()->with('error', 'Todavía no has guardado la clave secreta.');
        }

        $resultado = (new PasarelaStripe())->comprobarClave();

        return back()->with($resultado['ok'] ? 'exito' : 'error', $resultado['mensaje']);
    }

    public function borrarClave(Request $peticion)
    {
        $peticion->validate([
            'clave' => ['required', 'in:stripe_publica,stripe_secreto,stripe_webhook'],
        ]);

        ConfigPlataforma::where('clave', $peticion->input('clave'))->delete();

        return back()->with('exito', 'Clave eliminada.');
    }
}
