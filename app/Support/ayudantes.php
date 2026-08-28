<?php

use App\Models\ConfigEmpresa;
use App\Models\ConfigPlataforma;
use App\Support\SesionSalon;

if (! function_exists('config_empresa')) {
    /**
     * Ajuste de la empresa actual, con valor por defecto.
     *
     *   config_empresa('antelacion_min_horas', 2)
     */
    function config_empresa(string $clave, $porDefecto = null)
    {
        static $cache = null;

        if ($cache === null) {
            $cache = ConfigEmpresa::query()->pluck('valor', 'clave')->all();
        }

        $valor = $cache[$clave] ?? null;

        if ($valor === null || $valor === '') {
            return $porDefecto;
        }

        return match (true) {
            $valor === 'true'  => true,
            $valor === 'false' => false,
            is_numeric($valor) => $valor + 0,
            default            => $valor,
        };
    }
}

if (! function_exists('fijar_config_empresa')) {
    function fijar_config_empresa(string $clave, $valor): void
    {
        ConfigEmpresa::updateOrCreate(
            ['clave' => $clave],
            ['valor' => is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor]
        );
    }
}

if (! function_exists('config_terminal')) {
    /** Ajuste del terminal en el que se está trabajando. */
    function config_terminal(string $clave, $porDefecto = null)
    {
        static $cache = null;

        $terminal = SesionSalon::terminal();

        if (! $terminal) {
            return $porDefecto;
        }

        if ($cache === null) {
            $cache = $terminal->config()->pluck('valor', 'clave')->all();
        }

        $valor = $cache[$clave] ?? null;

        if ($valor === null || $valor === '') {
            return $porDefecto;
        }

        return match (true) {
            $valor === 'true'  => true,
            $valor === 'false' => false,
            is_numeric($valor) => $valor + 0,
            default            => $valor,
        };
    }
}

if (! function_exists('config_plataforma')) {
    /**
     * Ajuste global de la plataforma, guardado en la base central.
     *
     * Se consulta desde dentro del contexto de una empresa, así que hay
     * que salir a la conexión central: si no, buscaría la tabla en la
     * base de datos del salón y no existe allí.
     *
     *   config_plataforma('stripe_secreto')
     */
    function config_plataforma(string $clave, $porDefecto = null)
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if ($tenant) {
            tenancy()->central(function () use ($clave, &$valor, $porDefecto) {
                $valor = ConfigPlataforma::obtener($clave, $porDefecto);
            });

            return $valor ?? $porDefecto;
        }

        return ConfigPlataforma::obtener($clave, $porDefecto);
    }
}
    /**
     * Direccion del logotipo que toca mostrar.
     *
     * El del salon si lo ha subido; el de CLIMACO POS si no. Al borrar
     * el suyo, `logo` vuelve a null y aqui se cae solo al de la marca:
     * no hace falta ninguna comprobacion extra en las vistas.
     */
    if (! function_exists('logo_salon')) {
        function logo_salon(): string
        {
            $propio = tenant('logo');

            if (filled($propio)) {
                return route('panel.logotipo.ver');
            }

            return asset(\App\Services\GestorLogotipo::POR_DEFECTO);
        }
    }

    /** ¿El salon tiene logotipo propio, o se esta usando el nuestro? */
    if (! function_exists('logo_es_propio')) {
        function logo_es_propio(): bool
        {
            return filled(tenant('logo'));
        }
    }
    /**
     * Direccion del logotipo que toca mostrar.
     *
     * El del salon si lo ha subido; el de CLIMACO POS si no. Al borrar
     * el suyo, `logo` vuelve a null y aqui se cae solo al de la marca:
     * no hace falta ninguna comprobacion extra en las vistas.
     */
    if (! function_exists('logo_salon')) {
        function logo_salon(): string
        {
            $propio = tenant('logo');

            if (filled($propio)) {
                return route('panel.logotipo.ver');
            }

            return asset(\App\Services\GestorLogotipo::POR_DEFECTO);
        }
    }

    /** ¿El salon tiene logotipo propio, o se esta usando el nuestro? */
    if (! function_exists('logo_es_propio')) {
        function logo_es_propio(): bool
        {
            return filled(tenant('logo'));
        }
    }
