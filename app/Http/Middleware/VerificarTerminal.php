<?php

namespace App\Http\Middleware;

use App\Support\SesionSalon;
use Closure;
use Illuminate\Http\Request;

/**
 * Exige que el equipo este vinculado al salon.
 * Si no lo esta, manda a la pantalla de vinculacion, donde se piden
 * credenciales completas (opcion C, paso 1).
 */
class VerificarTerminal
{
    public function handle(Request $request, Closure $siguiente)
    {
        $vinculo = SesionSalon::terminalVinculado();

        if (! $vinculo) {
            return $request->expectsJson()
                ? response()->json(['error' => 'terminal_no_vinculado'], 403)
                : redirect()->route('panel.terminal.vincular');
        }

        $vinculo->registrarUso($request->ip());
        session([SesionSalon::CLAVE_TERMINAL => $vinculo->terminal_id]);

        return $siguiente($request);
    }
}
