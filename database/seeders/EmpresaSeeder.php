<?php

namespace Database\Seeders;

use Database\Seeders\Tenant\CatalogoPlantillaSeeder;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConfigSeeder::class,
            PerfilesSeeder::class,
            CatalogoPlantillaSeeder::class,
        ]);
    }
}
