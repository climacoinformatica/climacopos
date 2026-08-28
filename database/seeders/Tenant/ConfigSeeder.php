<?php

namespace Database\Seeders\Tenant;

use App\Models\ConfigEmpresa;
use Illuminate\Database\Seeder;

/**
 * Deja los ajustes de agenda con valores razonables para un salón
 * recién dado de alta. Todos son editables desde Ajustes.
 */
class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ConfigEmpresa::POR_DEFECTO as $clave => $valor) {
            ConfigEmpresa::firstOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
        }
    }
}
