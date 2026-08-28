<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;

/**
 * Los tres productos de CLIMACO.
 *
 * Se siembran una vez y luego se editan desde el panel de
 * administración: los textos comerciales cambian a menudo y no debería
 * hacer falta un despliegue para corregir una frase.
 */
class ProductosSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            [
                'slug'        => 'restaurant',
                'nombre'      => 'CLIMACO POS Restaurant',
                'reclamo'     => 'El TPV para bares y restaurantes',
                'descripcion' => 'Comandas desde la mesa, cocina al día y cierre de caja '
                               . 'sin cuadrar nada a mano. Pensado para el ritmo de un '
                               . 'servicio lleno, no para una oficina.',
                'modalidad'   => 'INSTALABLE',
                'sector'      => 'Hostelería',
                'color'       => '#f97316',
                'icono'       => 'restaurant',
                'caracteristicas' => [
                    'Mesas, salas y terrazas con plano visual',
                    'Comandas a cocina y barra por impresora o pantalla',
                    'División de cuenta y cambio de mesa sin perder líneas',
                    'Cierre de caja con arqueo y envío por correo',
                    'Cumple VERI*FACTU: huella encadenada y envío a la AEAT',
                    'Control horario del personal',
                    'Funciona sin internet: los datos están en tu equipo',
                ],
                'precio_desde' => null,
                'precio_nota'  => 'Licencia única, sin cuotas',
                'orden'        => 1,
            ],
            [
                'slug'        => 'gym',
                'nombre'      => 'CLIMACO Gym',
                'reclamo'     => 'Gestión para gimnasios y centros deportivos',
                'descripcion' => 'Socios, cuotas y accesos en un solo sitio. Sabes quién '
                               . 'está al día, quién lleva un mes sin venir y cuánto '
                               . 'ocupa cada clase antes de que empiece.',
                'modalidad'   => 'INSTALABLE',
                'sector'      => 'Deporte',
                'color'       => '#10b981',
                'icono'       => 'gym',
                'caracteristicas' => [
                    'Ficha de socio con altas, bajas y renovaciones',
                    'Cuotas periódicas y avisos de impago',
                    'Control de acceso por tarjeta o código',
                    'Clases dirigidas con aforo y reservas',
                    'Venta de productos y suplementos con stock',
                    'Cumple VERI*FACTU',
                    'Funciona sin internet: los datos están en tu equipo',
                ],
                'precio_desde' => null,
                'precio_nota'  => 'Licencia única, sin cuotas',
                'orden'        => 2,
            ],
            [
                'slug'        => 'beauty',
                'nombre'      => 'CLIMACO POS Beauty',
                'reclamo'     => 'Para peluquerías y centros de estética',
                'descripcion' => 'Agenda, caja y ficha técnica en la nube. Tus clientas '
                               . 'reservan solas desde el móvil y tú abres el salón con '
                               . 'el día ya organizado.',
                'modalidad'   => 'SAAS',
                'sector'      => 'Belleza',
                'color'       => '#8b5cf6',
                'icono'       => 'beauty',
                'caracteristicas' => [
                    'Agenda con huecos reales, contando pausas de tinte',
                    'Reserva online desde tu propia web',
                    'Ficha técnica: la fórmula exacta de cada color',
                    'Bonos, monedero y vales regalo',
                    'Cobro con tarjeta y anticipos online',
                    'Cumple VERI*FACTU',
                    'Control horario y vacaciones del equipo',
                    'Desde cualquier dispositivo, sin instalar nada',
                ],
                'precio_desde' => null,
                'precio_nota'  => 'Cuota mensual · primer mes de prueba',
                'descargable'  => false,
                'orden'        => 3,
            ],
        ];

        foreach ($productos as $datos) {
            Producto::updateOrCreate(['slug' => $datos['slug']], $datos);
        }
    }
}
