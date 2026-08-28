<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SelectorController extends Controller
{
    /** Rejilla de usuarios: la primera pantalla del programa. */
    public function mostrar()
    {
        $usuarios = Usuario::activos()
            ->with('perfil')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('panel.selector', [
            'usuarios' => $usuarios,
            'terminal' => SesionSalon::terminal(),
            'empresa'  => tenant(),
        ]);
    }

    /** Entrada con PIN. */
    public function entrar(Request $request)
    {
        $datos = $request->validate([
            'usuario_id' => ['required', 'integer'],
            'pin'        => ['required', 'string', 'min:4', 'max:8'],
        ]);

        $usuario = Usuario::activos()->find($datos['usuario_id']);

        if (! $usuario) {
            throw ValidationException::withMessages([
                'usuario_id' => 'Ese usuario no esta disponible.',
            ]);
        }

        if ($usuario->pinBloqueado()) {
            $minutos = (int) ceil(now()->diffInMinutes($usuario->pin_bloqueado_hasta, false));

            throw ValidationException::withMessages([
                'pin' => "Demasiados intentos. Vuelve a probar en {$minutos} minuto(s).",
            ]);
        }

        if (! $usuario->comprobarPin($datos['pin'])) {
            Auditoria::registrar('login_fallido', 'usuarios', $usuario->id,
                ['intentos' => $usuario->intentos_pin], $usuario->id);

            throw ValidationException::withMessages([
                'pin' => 'PIN incorrecto.',
            ]);
        }

        SesionSalon::entrar($usuario);

        return redirect()->intended(route('panel.inicio'));
    }

    public function salir()
    {
        SesionSalon::cerrar();

        return redirect()->route('panel.selector');
    }
}
