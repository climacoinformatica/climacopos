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

    // ------------------------------------------------------------------
    //  Facturas al mes
    // ------------------------------------------------------------------

    /**
     * Documentos emitidos este mes.
     *
     * Los de formacion no cuentan: quedan fuera solos por el global
     * scope, y ademas no tienen valor fiscal.
     */
    public static function facturasDelMes(): int
    {
        return \App\Models\Ticket::where('estado', 'COBRADO')
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /** Cuantas quedan. Null si el plan no limita. */
    public static function facturasDisponibles(): ?int
    {
        $maximo = self::maximo('max_facturas_mes');

        if ($maximo === null) {
            return null;
        }

        return max(0, $maximo - self::facturasDelMes());
    }

    /**
     * ¿Se ha alcanzado el limite?
     *
     * IMPORTANTE: esto NO impide facturar.
     *
     * Bloquear el cobro dejaria al salon sin poder atender a una clienta
     * que ya esta delante, y sin ticket que darle. Eso es un problema
     * fiscal para el, no solo comercial.
     *
     * Lo que se bloquea es el PANEL —agenda, informes, catalogo— hasta
     * que amplie el plan. El programa deja de ser comodo, pero nunca
     * impide cobrar.
     */
    public static function facturasAgotadas(): bool
    {
        $quedan = self::facturasDisponibles();

        return $quedan !== null && $quedan <= 0;
    }

    /**
     * Aviso cuando quedan pocas.
     *
     * Dos avisos: uno al 80 por ciento y otro al 95. Da tiempo a
     * reaccionar antes de quedarse sin margen, en lugar de encontrarse el
     * panel bloqueado un lunes por la manana.
     */
    public static function avisoFacturas(): ?array
    {
        $maximo = self::maximo('max_facturas_mes');

        if ($maximo === null) {
            return null;
        }

        $usadas = self::facturasDelMes();
        $porcentaje = $maximo > 0 ? ($usadas / $maximo) * 100 : 0;

        if ($porcentaje < 80) {
            return null;
        }

        return [
            'usadas'  => $usadas,
            'maximo'  => $maximo,
            'quedan'  => max(0, $maximo - $usadas),
            'grave'   => $porcentaje >= 95,
            'agotado' => $usadas >= $maximo,
        ];
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
            'facturas'          => self::facturasDelMes(),
            'facturas_max'      => self::maximo('max_facturas_mes'),
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
