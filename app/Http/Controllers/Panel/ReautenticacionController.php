<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Paso 3 de la opcion C: contrasena para acciones sensibles.
 * Vale durante Usuario::MINUTOS_REAUTENTICACION y se pierde al
 * cambiar de usuario.
 */
class ReautenticacionController extends Controller
{
    public function mostrar()
    {
        $usuario = SesionSalon::usuario();

        if ($usuario && blank($usuario->password)) {
            return redirect()->route('panel.inicio')->with('error',
                'Tu usuario no tiene contrasena definida. Pidele al propietario que te la asigne.');
        }

        return view('panel.reautenticar', [
            'usuario' => $usuario,
            'destino' => session('salon.destino_reauth'),
        ]);
    }

    public function confirmar(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $usuario = SesionSalon::usuario();

        if (! $usuario || ! $usuario->comprobarPassword($request->input('password'))) {
            Auditoria::registrar('reautenticacion_fallida');

            throw ValidationException::withMessages([
                'password' => 'Contrasena incorrecta.',
            ]);
        }

        SesionSalon::marcarReautenticado();
        Auditoria::registrar('reautenticacion');

        $destino = session()->pull('salon.destino_reauth', route('panel.inicio'));

        return redirect()->to($destino);
    }
}
