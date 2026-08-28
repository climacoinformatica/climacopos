<?php

namespace Database\Seeders\Tenant;

use App\Models\Perfil;
use App\Support\Permisos;
use Illuminate\Database\Seeder;

/**
 * Se ejecuta DENTRO de la base de datos de cada empresa nueva.
 * Deja los cinco perfiles de fabrica listos para que el propietario
 * pueda invitar a su equipo sin configurar nada.
 */
class PerfilesSeeder extends Seeder
{
    public function run(): void
    {
        $orden = 0;

        foreach (Permisos::perfilesDeFabrica() as $clave => $datos) {
            Perfil::updateOrCreate(
                ['clave' => $clave],
                [
                    'nombre'     => $datos['nombre'],
                    'permisos'   => $datos['permisos'],
                    'es_sistema' => true,
                    'orden'      => ++$orden,
                ]
            );
        }
    }
}
