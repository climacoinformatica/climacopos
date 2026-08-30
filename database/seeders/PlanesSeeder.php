<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Producto;
use Illuminate\Database\Seeder;

/**
 * Los planes de los tres productos.
 *
 * Se diferencian SOLO en el soporte: las funcionalidades van completas en
 * los tres. Se cobra por el soporte, que es lo que de verdad cuesta
 * tiempo.
 *
 * Es idempotente: se puede ejecutar las veces que haga falta sin
 * duplicar nada, y sin pisar los precios si ya se cambiaron desde el
 * panel de administracion.
 */
class PlanesSeeder extends Seeder
{
    public function run(): void
    {
        $plantilla = [
            [
                'slug'        => 'basico',
                'nombre'      => 'Básico',
                'precio_mes'  => 18.00,
                'soporte'     => 'NINGUNO',
                'soporte_texto' => 'Sin soporte incluido',
                'descripcion' => 'Todo el programa, sin soporte.',
                'orden'       => 1,
            ],
            [
                'slug'        => 'starter',
                'nombre'      => 'Starter',
                'precio_mes'  => 25.00,
                'soporte'     => 'EMAIL',
                'soporte_texto' => 'Soporte por correo electrónico',
                'descripcion' => 'Todo el programa, con soporte por correo.',
                'orden'       => 2,
            ],
            [
                'slug'        => 'profesional',
                'nombre'      => 'Profesional',
                'precio_mes'  => 35.00,
                'soporte'     => 'COMPLETO',
                'soporte_texto' => 'Soporte por teléfono, correo y remoto',
                'descripcion' => 'Todo el programa, con soporte completo.',
                'orden'       => 3,
            ],
        ];

        foreach (Producto::all() as $producto) {
            foreach ($plantilla as $datos) {
                Plan::firstOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'slug'        => $datos['slug'],
                    ],
                    array_merge($datos, [
                        /**
                         * Sin limites: las funcionalidades van completas
                         * en los tres planes.
                         *
                         * Se dejan valores altos en lugar de quitar las
                         * columnas, por si algun dia se quiere limitar
                         * algo sin migrar la tabla.
                         */
                        'max_profesionales'     => 999,
                        'max_terminales'        => 999,
                        'max_almacenamiento_mb' => 20480,
                        'reservas_online'       => true,
                        'pagos_online'          => true,
                        'verifactu'             => true,
                        'informes_avanzados'    => true,
                        'dominio_propio'        => false,

                        // Dos meses gratis al pagar el ano por adelantado
                        'precio_ano' => round($datos['precio_mes'] * 10, 2),

                        'activo' => true,
                    ]),
                );
            }
        }
    }
}
