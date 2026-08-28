<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanesSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'nombre'             => 'Basico',
                'slug'               => 'basico',
                'precio_mes'         => 19.00,
                'precio_ano'         => 190.00,
                'max_profesionales'  => 2,
                'max_terminales'     => 1,
                'max_almacenamiento_mb' => 500,
                'sms_incluidos'      => 0,
                'reservas_online'    => true,
                'pagos_online'       => false,
                'verifactu'          => true,
                'dominio_propio'     => false,
                'informes_avanzados' => false,
                'orden'              => 1,
            ],
            [
                'nombre'             => 'Profesional',
                'slug'               => 'profesional',
                'precio_mes'         => 39.00,
                'precio_ano'         => 390.00,
                'max_profesionales'  => 6,
                'max_terminales'     => 2,
                'max_almacenamiento_mb' => 2000,
                'sms_incluidos'      => 100,
                'reservas_online'    => true,
                'pagos_online'       => true,
                'verifactu'          => true,
                'dominio_propio'     => true,
                'informes_avanzados' => true,
                'orden'              => 2,
            ],
            [
                'nombre'             => 'Salon',
                'slug'               => 'salon',
                'precio_mes'         => 69.00,
                'precio_ano'         => 690.00,
                'max_profesionales'  => 20,
                'max_terminales'     => 5,
                'max_almacenamiento_mb' => 10000,
                'sms_incluidos'      => 500,
                'reservas_online'    => true,
                'pagos_online'       => true,
                'verifactu'          => true,
                'dominio_propio'     => true,
                'informes_avanzados' => true,
                'orden'              => 3,
            ],
        ];

        foreach ($planes as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
