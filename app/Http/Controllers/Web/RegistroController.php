<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Services\Correo\CorreosPlataforma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registro y acceso al area de clientes.
 *
 * Una sola cuenta para los tres productos: quien compra el TPV de
 * hosteleria y manana prueba el de peluquerias no deberia registrarse
 * dos veces ni recordar dos contrasenas.
 */
class RegistroController extends Controller
{
    public function formulario()
    {
        return view('web.registro');
    }

    public function registrar(Request $peticion)
    {
        $datos = $peticion->validate([
            'nombre'    => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:160', 'unique:cuentas,email'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'telefono'  => ['nullable', 'string', 'max:30'],
            'empresa'   => ['nullable', 'string', 'max:120'],
            'provincia' => ['nullable', 'string', 'max:60'],
            'sector'    => ['nullable', 'string', 'max:60'],
            'acepta'    => ['accepted'],
        ], [
            'email.unique'  => 'Ya hay una cuenta con ese correo. ¿Quieres entrar?',
            'acepta.accepted' => 'Hay que aceptar las condiciones para continuar.',
        ]);

        $cuenta = Cuenta::create([
            'nombre'    => $datos['nombre'],
            'email'     => $datos['email'],
            'password'  => Hash::make($datos['password']),
            'telefono'  => $datos['telefono'] ?? null,
            'empresa'   => $datos['empresa'] ?? null,
            'provincia' => $datos['provincia'] ?? null,
            'sector'    => $datos['sector'] ?? null,
            'acepta_novedades' => $peticion->boolean('novedades'),

            /**
             * La cuenta nace SIN verificar.
             *
             * Con registro abierto, sin este paso cualquiera crearia
             * cuentas con correos ajenos. Y para el SaaS es mas serio:
             * cada alta acaba provisionando una base de datos.
             */
            'token_verificacion' => Str::random(64),
            'email_verified_at' => null,
        ]);

        try {
            (new CorreosPlataforma())->verificarCuenta($cuenta);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo enviar la verificacion', [
                'cuenta' => $cuenta->email,
                'error'  => $e->getMessage(),
            ]);
        }

        return redirect()->route('web.registro.enviado')
            ->with('email', $cuenta->email);
    }

    public function enviado()
    {
        return view('web.registro-enviado', ['email' => session('email')]);
    }

    /** Enlace del correo de verificacion. */
    public function verificar(string $token)
    {
        $cuenta = Cuenta::where('token_verificacion', $token)->first();

        if (! $cuenta) {
            return view('web.verificacion', ['ok' => false]);
        }

        $cuenta->update([
            'email_verified_at' => now(),
            'token_verificacion'  => null,
        ]);

        auth('cuenta')->login($cuenta);

        return redirect()->route('web.area')
            ->with('exito', 'Cuenta verificada. Ya puedes descargar.');
    }

    // ------------------------------------------------------------------

    public function acceso()
    {
        return view('web.acceso');
    }

    public function entrar(Request $peticion)
    {
        $datos = $peticion->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $cuenta = Cuenta::where('email', $datos['email'])->first();

        if (! $cuenta || ! Hash::check($datos['password'], $cuenta->password)) {
            return back()->withInput($peticion->only('email'))
                ->with('error', 'El correo o la contraseña no son correctos.');
        }

        if (! $cuenta->email_verified_at) {
            return back()->withInput($peticion->only('email'))
                ->with('error', 'Todavía no has confirmado tu correo. '
                    . 'Revisa la bandeja de entrada, y también la carpeta de spam.');
        }

        auth('cuenta')->login($cuenta, $peticion->boolean('recordar'));

        $peticion->session()->regenerate();

        return redirect()->intended(route('web.area'));
    }

    public function salir(Request $peticion)
    {
        auth('cuenta')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect()->route('web.inicio');
    }

    /** Reenvia el correo de verificacion. */
    public function reenviar(Request $peticion)
    {
        $peticion->validate(['email' => ['required', 'email']]);

        $cuenta = Cuenta::where('email', $peticion->input('email'))
            ->whereNull('email_verified_at')
            ->first();

        /**
         * Se responde lo mismo exista o no la cuenta.
         *
         * Si dijeramos «ese correo no esta registrado», cualquiera podria
         * averiguar quien es cliente probando direcciones.
         */
        if ($cuenta) {
            $cuenta->update(['token_verificacion' => Str::random(64)]);

            try {
                (new CorreosPlataforma())->verificarCuenta($cuenta);
            } catch (\Throwable) {
            }
        }

        return back()->with('exito',
            'Si esa dirección está pendiente de verificar, te hemos enviado el enlace.');
    }

    // ------------------------------------------------------------------
    //  Recuperar el acceso
    // ------------------------------------------------------------------

    public function olvidada()
    {
        return view('web.olvidada');
    }

    /**
     * Envia el enlace de recuperacion.
     *
     * Se responde SIEMPRE lo mismo, exista o no la cuenta. Si dijeramos
     * «ese correo no esta registrado», cualquiera podria averiguar quien
     * es cliente probando direcciones.
     */
    public function enviarEnlace(Request $peticion)
    {
        $datos = $peticion->validate([
            'email' => ['required', 'email'],
        ]);

        $cuenta = Cuenta::where('email', $datos['email'])->first();

        if ($cuenta) {
            /**
             * El token se guarda HASHEADO.
             *
             * Es una llave que abre la cuenta durante una hora: un volcado
             * de la base no debe permitir usarla.
             */
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $cuenta->email],
                [
                    'token'      => hash('sha256', $token),
                    'created_at' => now(),
                ],
            );

            try {
                (new CorreosPlataforma())->recuperarAcceso(
                    $cuenta,
                    route('web.restablecer', ['token' => $token]) . '?email=' . urlencode($cuenta->email),
                );
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar la recuperacion', [
                    'cuenta' => $cuenta->email,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return back()->with('exito',
            'Si esa direccion tiene cuenta, te hemos enviado un enlace para '
            . 'volver a entrar. Mira tambien la carpeta de spam.');
    }

    public function restablecer(Request $peticion, string $token)
    {
        return view('web.restablecer', [
            'token' => $token,
            'email' => $peticion->input('email'),
        ]);
    }

    public function guardarNueva(Request $peticion)
    {
        $datos = $peticion->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min'       => 'La contrasena necesita al menos ocho caracteres.',
            'password.confirmed' => 'Las dos contrasenas no coinciden.',
        ]);

        $registro = DB::table('password_reset_tokens')
            ->where('email', $datos['email'])
            ->first();

        if (! $registro || ! hash_equals($registro->token, hash('sha256', $datos['token']))) {
            return back()->with('error',
                'Ese enlace ya no vale. Pide uno nuevo desde «he olvidado mi contrasena».');
        }

        /**
         * Una hora de validez.
         *
         * Lo justo para abrir el correo con calma, y poco para que un
         * enlace olvidado en una bandeja siga abriendo la cuenta meses
         * despues.
         */
        if (now()->diffInMinutes(\Illuminate\Support\Carbon::parse($registro->created_at)) > 60) {
            return back()->with('error',
                'Ese enlace ha caducado. Pide uno nuevo, es cuestion de un minuto.');
        }

        $cuenta = Cuenta::where('email', $datos['email'])->first();

        if (! $cuenta) {
            return back()->with('error', 'Ese enlace ya no vale.');
        }

        $cuenta->update([
            'password' => Hash::make($datos['password']),

            // Si llego aqui es que lee su correo: se da por verificada
            'email_verified_at' => $cuenta->email_verified_at ?? now(),
        ]);

        // El token se usa UNA vez
        DB::table('password_reset_tokens')->where('email', $datos['email'])->delete();

        try {
            (new CorreosPlataforma())->contrasenaCambiada($cuenta);
        } catch (\Throwable) {
        }

        auth('cuenta')->login($cuenta);

        $peticion->session()->regenerate();

        return redirect()->route('web.area')
            ->with('exito', 'Contrasena cambiada. Ya estas dentro.');
    }

    // ------------------------------------------------------------------
    //  Cambiar la contrasena estando dentro
    // ------------------------------------------------------------------

    public function cambiarPassword(Request $peticion)
    {
        $cuenta = auth('cuenta')->user();

        $datos = $peticion->validate([
            'actual'   => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'actual.required'    => 'Escribe tu contrasena actual.',
            'password.min'       => 'La nueva necesita al menos ocho caracteres.',
            'password.confirmed' => 'Las dos contrasenas nuevas no coinciden.',
        ]);

        /**
         * Se pide la actual aunque ya tenga la sesion abierta.
         *
         * Si alguien se deja el ordenador sin bloquear, sin esto podria
         * cambiarle la contrasena y dejarle fuera de su propio salon.
         */
        if (! Hash::check($datos['actual'], $cuenta->password)) {
            return back()->with('error', 'La contrasena actual no es correcta.');
        }

        $cuenta->update(['password' => Hash::make($datos['password'])]);

        try {
            (new CorreosPlataforma())->contrasenaCambiada($cuenta);
        } catch (\Throwable) {
        }

        return back()->with('exito', 'Contrasena cambiada.');
    }
}
