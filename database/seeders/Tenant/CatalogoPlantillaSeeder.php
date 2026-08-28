<?php

namespace Database\Seeders\Tenant;

use App\Models\Articulo;
use App\Models\Familia;
use App\Support\PlantillasCatalogo;
use Illuminate\Database\Seeder;

/**
 * Precarga el catálogo según el tipo de negocio de la empresa.
 * Se ejecuta al crear la empresa, después de PerfilesSeeder.
 *
 * No hace nada si ya hay familias: así se puede volver a lanzar
 * `tenants:seed` sin duplicar el catálogo de un salón en marcha.
 */
class CatalogoPlantillaSeeder extends Seeder
{
    public function run(): void
    {
        if (Familia::count() > 0) {
            return;
        }

        $empresa = tenant();
        $tipo    = $empresa?->tipo_negocio ?? 'PELUQUERIA';
        $impuesto = ($empresa?->regimen_fiscal ?? 'IGIC') === 'IGIC' ? 7.00 : 21.00;

        $ordenFamilia = 0;

        foreach (PlantillasCatalogo::para($tipo) as $bloque) {
            $familia = Familia::create([
                'nombre'         => $bloque['familia'],
                'tipo'           => $bloque['tipo'] ?? 'SERVICIO',
                'color'          => $bloque['color'] ?? '#6366f1',
                'orden'          => ++$ordenFamilia,
                'visible_online' => ($bloque['tipo'] ?? 'SERVICIO') !== 'PRODUCTO',
            ]);

            $orden = 0;

            foreach ($bloque['servicios'] ?? [] as [$nombre, $duracion, $pausa, $final, $precio]) {
                Articulo::create([
                    'familia_id'             => $familia->id,
                    'tipo'                   => 'SERVICIO',
                    'nombre'                 => $nombre,
                    'precio'                 => $precio,
                    'impuesto_pct'           => $impuesto,
                    'duracion_min'           => $duracion,
                    'tiempo_pausa_min'       => $pausa,
                    'tiempo_final_min'       => $final,
                    'permite_reserva_online' => true,
                    'orden'                  => ++$orden,
                ]);
            }

            foreach ($bloque['productos'] ?? [] as [$nombre, $precio]) {
                Articulo::create([
                    'familia_id'             => $familia->id,
                    'tipo'                   => 'PRODUCTO',
                    'nombre'                 => $nombre,
                    'precio'                 => $precio,
                    'impuesto_pct'           => $impuesto,
                    'control_stock'          => true,
                    'stock'                  => 0,
                    'stock_min'              => 2,
                    'permite_reserva_online' => false,
                    'duracion_min'           => 0,
                    'orden'                  => ++$orden,
                ]);
            }
        }
    }
}
