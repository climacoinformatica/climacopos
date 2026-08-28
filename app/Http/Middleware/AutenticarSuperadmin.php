<?php

namespace App\Http\Middleware;

use App\Models\Cuenta;
use Closure;
use Illuminate\Http\Request;

/**
 * Protege el panel de superadministración.
 *
 * Solo cuentas con es_superadmin. Es la zona desde donde se ven todas
 * las empresas y se configuran las claves de cobro de la plataforma,
 * así que se comprueba en cada petición, no solo al entrar.
 */
class AutenticarSuperadmin
{
    public const CLAVE_SESION = 'admin.cuenta_id';

    public function handle(Request $peticion, Closure $siguiente)
    {
        $id = session(self::CLAVE_SESION);

        $cuenta = $id ? Cuenta::find($id) : null;

        if (! $cuenta || ! $cuenta->es_superadmin) {
            session()->forget(self::CLAVE_SESION);

            return $peticion->expectsJson()
                ? response()->json(['error' => 'no autorizado'], 401)
                : redirect()->route('admin.acceso');
        }

        $peticion->attributes->set('superadmin', $cuenta);
        view()->share('superadmin', $cuenta);

        return $siguiente($peticion);
    }
}
