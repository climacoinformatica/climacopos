<?php

namespace App\Support;

use App\Models\Terminal;
use App\Models\Usuario;
use RuntimeException;

/**
 * Límites de cada plan.
 *
 * Los planes definían límites desde la Fase 0 pero nadie los comprobaba.
 * Aquí se centralizan para que la respuesta sea siempre la misma y el
 * mensaje explique qué hacer, no solo que no se puede.
 */
class LimitesPlan
{
    /**
     * ¿Cuántos profesionales caben todavía?
     * Devuelve null si el plan no tiene límite.
     */
    public static function profesionalesDisponibles(): ?int
    {
        $maximo = self::maximo('max_profesionales');

        if ($maximo === null) {
            return null;
        }

        return max(0, $maximo - Usuario::activos()->profesionales()->count());
    }

    public static function terminalesDisponibles(): ?int
    {
        $maximo = self::maximo('max_terminales');

        if ($maximo === null) {
            return null;
        }

        return max(0, $maximo - Terminal::where('activo', true)->count());
    }

    /**
     * Lanza excepción si no cabe otro profesional.
     *
     * Se comprueba solo al dar de alta, nunca al editar: si alguien baja
     * de plan teniendo cinco profesionales, no se le pueden desactivar
     * dos por sorpresa. Se le impide añadir más y ya irá ajustando.
     */
    public static function comprobarProfesional(): void
    {
        $quedan = self::profesionalesDisponibles();

        if ($quedan !== null && $quedan <= 0) {
            $plan = tenant()->plan;

            throw new RuntimeException(
                'Tu plan ' . ($plan?->nombre ?? 'actual') . ' permite '
                . self::maximo('max_profesionales') . ' profesional(es). '
                . 'Para añadir más, cambia de plan desde Suscripción.'
            );
        }
    }

    public static function comprobarTerminal(): void
    {
        $quedan = self::terminalesDisponibles();

        if ($quedan !== null && $quedan <= 0) {
            $plan = tenant()->plan;

            throw new RuntimeException(
                'Tu plan ' . ($plan?->nombre ?? 'actual') . ' permite '
                . self::maximo('max_terminales') . ' terminal(es). '
                . 'Para vincular otro equipo, cambia de plan desde Suscripción.'
            );
        }
    }

    /** ¿El plan incluye esta función? */
    public static function incluye(string $funcion): bool
    {
        $plan = tenant()?->plan;

        if (! $plan) {
            // Sin plan asignado (prueba recién creada): todo disponible
            return true;
        }

        return (bool) ($plan->{$funcion} ?? true);
    }

    public static function comprobarFuncion(string $funcion, string $nombreLegible): void
    {
        if (! self::incluye($funcion)) {
            throw new RuntimeException(
                $nombreLegible . ' no está incluido en tu plan. '
                . 'Puedes activarlo cambiando de plan desde Suscripción.'
            );
        }
    }

    /** Resumen para la pantalla de suscripción. */
    public static function resumen(): array
    {
        $plan = tenant()?->plan;

        return [
            'plan'              => $plan?->nombre,
            'profesionales'     => Usuario::activos()->profesionales()->count(),
            'profesionales_max' => self::maximo('max_profesionales'),
            'terminales'        => Terminal::where('activo', true)->count(),
            'terminales_max'    => self::maximo('max_terminales'),
            'reservas_online'   => self::incluye('reservas_online'),
            'pagos_online'      => self::incluye('pagos_online'),
            'verifactu'         => self::incluye('verifactu'),
        ];
    }

    protected static function maximo(string $campo): ?int
    {
        $plan = tenant()?->plan;

        if (! $plan) {
            return null;
        }

        $valor = (int) ($plan->{$campo} ?? 0);

        // 0 o negativo se interpreta como «sin límite»
        return $valor > 0 ? $valor : null;
    }
}
