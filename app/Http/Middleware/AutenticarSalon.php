<?php

namespace App\Http\Middleware;

use App\Support\SesionSalon;
use Closure;
use Illuminate\Http\Request;

/**
 * Exige que haya un empleado con sesion abierta (paso 2 de la opcion C).
 * Comparte la vista con el selector de usuario: si no hay nadie dentro,
 * se vuelve a la rejilla de fotos.
 */
class AutenticarSalon
{
    public function handle(Request $request, Closure $siguiente)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario) {
            return $request->expectsJson()
                ? response()->json(['error' => 'sesion_caducada'], 401)
                : redirect()->route('panel.selector');
        }

        // Disponible en vistas y controladores como $usuarioSalon
        $request->attributes->set('usuarioSalon', $usuario);
        view()->share('usuarioSalon', $usuario);

        return $siguiente($request);
    }
}
