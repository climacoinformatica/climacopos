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
    public function mostrar(Request $peticion)
    {
        $usuario = SesionSalon::usuario();

        /**
         * Destino por parametro, ademas del que deja el middleware.
         *
         * El middleware guarda la direccion de vuelta cuando corta una
         * navegacion normal, pero no cuando la peticion venia por
         * fetch: ahi devuelve un 423 y es el JavaScript quien manda
         * aqui al usuario. Sin esto, quien pulsaba «Cerrar dia» en el
         * TPV acababa en el Inicio tras poner la contrasena, y tenia
         * que volver al TPV y empezar otra vez.
         *
         * Solo se aceptan rutas internas: un destino absoluto permitiria
         * mandar a la gente a otro sitio tras autenticarse.
         */
        $destino = (string) $peticion->query('destino');

        if (str_starts_with($destino, '/') && ! str_starts_with($destino, '//')) {
            session(['salon.destino_reauth' => $destino]);
        }

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
