<?php

namespace Database\Seeders;

use Database\Seeders\Tenant\CatalogoPlantillaSeeder;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Database\Seeder;

/**
 * Datos de arranque de un salon recien creado.
 *
 * POR QUE EXISTE ESTE FICHERO
 *
 * stancl/tenancy ejecuta un seeder al crear cada tenant, y la clase que
 * ejecuta se indica en config/tenancy.php. Venia apuntando al
 * DatabaseSeeder de la plantilla de Laravel, que crea un usuario de
 * prueba y nada mas: la base del salon quedaba migrada pero SIN PERFILES,
 * y entonces no se podia dar de alta al propietario.
 *
 * En local no se notaba porque actualizar.ps1 ejecuta `tenants:seed`
 * despues de migrar, asi que las bases acababan sembradas por otra via.
 * El alta automatica desde la web no pasa por ahi.
 *
 * ORDEN
 *
 * Los perfiles van PRIMERO: el catalogo y la configuracion no dependen
 * de ellos, pero el usuario propietario que se crea justo despues si.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PerfilesSeeder::class,
            ConfigSeeder::class,
            CatalogoPlantillaSeeder::class,
        ]);
    }
}
