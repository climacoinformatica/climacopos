<?php

namespace App\Support;

use App\Models\Auditoria;
use App\Models\Terminal;
use App\Models\TerminalVinculo;
use App\Models\Usuario;
use Illuminate\Support\Facades\Cookie;

/**
 * Sesion del panel del salon (opcion C).
 *
 *   1. El EQUIPO se vincula una vez con credenciales completas
 *      -> cookie de larga duracion con el token del terminal.
 *   2. El EMPLEADO entra con PIN en cada turno
 *      -> sesion de navegador.
 *   3. Las acciones sensibles piden CONTRASENA
 *      -> marca temporal de reautenticacion, valida 15 minutos.
 */
class SesionSalon
{
    public const CLAVE_USUARIO   = 'salon.usuario_id';
    public const CLAVE_TERMINAL  = 'salon.terminal_id';
    public const CLAVE_REAUTH    = 'salon.reautenticado_en';

    // ------------------------------------------------------------------
    // Terminal
    // ------------------------------------------------------------------

    public static function terminalVinculado(): ?TerminalVinculo
    {
        $vinculo = TerminalVinculo::porToken(request()->cookie(TerminalVinculo::COOKIE));

        if ($vinculo && ! $vinculo->terminal?->activo) {
            return null;
        }

        return $vinculo;
    }

    public static function vincularTerminal(Terminal $terminal, ?Usuario $usuario = null): void
    {
        [$vinculo, $tokenPlano] = TerminalVinculo::emitir(
            $terminal,
            $usuario,
            request()->userAgent()
        );

        Cookie::queue(Cookie::make(
            name: TerminalVinculo::COOKIE,
            value: $tokenPlano,
            minutes: TerminalVinculo::DIAS_VALIDEZ * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        ));

        session([self::CLAVE_TERMINAL => $terminal->id]);

        Auditoria::registrar('terminal_vinculado', 'terminales', $terminal->id, [
            'vinculo_id'  => $vinculo->id,
            'dispositivo' => $vinculo->dispositivo,
        ], $usuario?->id);
    }

    public static function terminal(): ?Terminal
    {
        $id = session(self::CLAVE_TERMINAL);

        return $id ? Terminal::find($id) : null;
    }

    // ------------------------------------------------------------------
    // Usuario
    // ------------------------------------------------------------------

    public static function usuario(): ?Usuario
    {
        $id = session(self::CLAVE_USUARIO);

        if (! $id) {
            return null;
        }

        $usuario = Usuario::with('perfil')->find($id);

        // Si lo han desactivado mientras tenia sesion abierta, fuera.
        if (! $usuario || $usuario->estado !== 'ACTIVO') {
            self::cerrar();

            return null;
        }

        return $usuario;
    }

    public static function entrar(Usuario $usuario): void
    {
        session()->regenerate();
        session([self::CLAVE_USUARIO => $usuario->id]);
        session()->forget(self::CLAVE_REAUTH);

        Auditoria::registrar('login', 'usuarios', $usuario->id, ['via' => 'pin'], $usuario->id);
    }

    public static function cerrar(): void
    {
        $usuarioId = session(self::CLAVE_USUARIO);

        session()->forget([self::CLAVE_USUARIO, self::CLAVE_REAUTH]);
        session()->regenerate();

        if ($usuarioId) {
            Auditoria::registrar('logout', 'usuarios', $usuarioId, null, $usuarioId);
        }
    }

    // ------------------------------------------------------------------
    // Reautenticacion por contrasena
    // ------------------------------------------------------------------

    public static function marcarReautenticado(): void
    {
        session([self::CLAVE_REAUTH => now()->timestamp]);
    }

    public static function reautenticacionVigente(): bool
    {
        $marca = session(self::CLAVE_REAUTH);

        if (! $marca) {
            return false;
        }

        return (now()->timestamp - (int) $marca) < (Usuario::MINUTOS_REAUTENTICACION * 60);
    }

    public static function olvidarReautenticacion(): void
    {
        session()->forget(self::CLAVE_REAUTH);
    }
}
