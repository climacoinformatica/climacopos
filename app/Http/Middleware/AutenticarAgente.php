<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentica al Agente CLIMACO por token.
 *
 * El token se genera al vincular el terminal y se guarda hasheado:
 * un volcado de la base de datos no permite suplantar a un agente.
 */
class AutenticarAgente
{
    public function handle(Request $peticion, Closure $siguiente)
    {
        $token = $peticion->bearerToken() ?: $peticion->header('X-Agente-Token');

        if (blank($token)) {
            return response()->json(['error' => 'Falta el token del agente.'], 401);
        }

        $terminal = Terminal::where('agente_token', hash('sha256', $token))
            ->where('activo', true)
            ->first();

        if (! $terminal) {
            return response()->json(['error' => 'Token no válido o terminal desactivado.'], 401);
        }

        $peticion->attributes->set('terminal', $terminal);

        return $siguiente($peticion);
    }
}
