<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AutenticarSuperadmin;
use App\Models\Cuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AccesoController extends Controller
{
    public function mostrar()
    {
        if (session(AutenticarSuperadmin::CLAVE_SESION)) {
            return redirect()->route('admin.inicio');
        }

        return view('admin.acceso');
    }

    public function entrar(Request $peticion)
    {
        $datos = $peticion->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /**
         * Limitación de intentos.
         *
         * Esta pantalla da acceso a las claves de cobro de todos los
         * salones: cinco intentos por minuto y dirección IP.
         */
        $llave = 'admin|' . $peticion->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera '
                           . RateLimiter::availableIn($llave) . ' segundos.',
            ]);
        }

        $cuenta = Cuenta::where('email', $datos['email'])->first();

        if (! $cuenta || ! $cuenta->es_superadmin || ! Hash::check($datos['password'], $cuenta->password)) {
            RateLimiter::hit($llave, 60);

            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas.',
            ]);
        }

        RateLimiter::clear($llave);

        session()->regenerate();
        session([AutenticarSuperadmin::CLAVE_SESION => $cuenta->id]);

        $cuenta->forceFill(['ultimo_acceso_admin' => now()])->saveQuietly();

        return redirect()->route('admin.inicio');
    }

    public function salir()
    {
        session()->forget(AutenticarSuperadmin::CLAVE_SESION);
        session()->regenerate();

        return redirect()->route('admin.acceso');
    }
}
