<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Producto;
use Illuminate\Database\Seeder;

/**
 * El plan gratuito, para el producto en la nube.
 *
 * Es la puerta de entrada: permite probar el programa completo sin
 * compromiso, con dos limites que un salon real supera enseguida.
 *
 * Cien facturas al mes son unas cinco al dia. Una peluqueria pequena
 * anda por ahi, asi que en cuanto crezca un poco tendra que pasar a un
 * plan de pago. Es un limite honesto: sirve de verdad para empezar, y no
 * da para vivir en el.
 */
class PlanGratuitoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Producto::where('modalidad', 'SAAS')->get() as $producto) {
            Plan::updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'slug'        => 'gratuito',
                ],
                [
                    'nombre'        => 'Gratuito',
                    'precio_mes'    => 0,
                    'precio_ano'    => 0,
                    'es_gratuito'   => true,

                    'soporte'       => 'NINGUNO',
                    'soporte_texto' => 'Sin soporte incluido',
                    'descripcion'   => 'Para empezar y probarlo con calma.',

                    // Los dos limites del gratuito
                    'max_profesionales' => 1,
                    'max_facturas_mes'  => 100,

                    // Lo demas va completo: se prueba el programa entero
                    'max_terminales'        => 1,
                    'max_almacenamiento_mb' => 1024,
                    'reservas_online'       => true,
                    'pagos_online'          => true,
                    'verifactu'             => true,
                    'informes_avanzados'    => true,

                    // Primero en la lista
                    'orden'  => 0,
                    'activo' => true,
                ],
            );
        }
    }
}
