<?php

namespace App\Http\Middleware;

use App\Models\Auditoria;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Comprueba un permiso y, si el permiso esta en la lista de sensibles,
 * exige ademas reautenticacion con contrasena (paso 3 de la opcion C).
 *
 * Uso en rutas:
 *   ->middleware('permiso:tpv.anular_ticket')
 *   ->middleware('permiso:informes.ver,informes.ver_propios')   // basta uno
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $siguiente, string ...$claves)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario) {
            return redirect()->route('panel.selector');
        }

        if (! $usuario->tieneAlgunPermiso($claves)) {
            Auditoria::registrar('permiso_denegado', detalle: [
                'permisos' => $claves,
                'ruta'     => $request->path(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'sin_permiso',
                    'mensaje' => 'Tu perfil no permite esta accion.',
                ], 403);
            }

            throw new AccessDeniedHttpException('Tu perfil no permite esta accion.');
        }

        // Escalada: los permisos sensibles piden contrasena aunque el
        // empleado haya entrado con PIN.
        $sensible = collect($claves)->contains(fn ($c) => Permisos::exigePassword($c));

        if ($sensible && ! SesionSalon::reautenticacionVigente()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'requiere_password',
                    'mensaje' => 'Confirma tu contrasena para continuar.',
                ], 423);
            }

            session(['salon.destino_reauth' => $request->fullUrl()]);

            return redirect()->route('panel.reautenticar');
        }

        return $siguiente($request);
    }
}
