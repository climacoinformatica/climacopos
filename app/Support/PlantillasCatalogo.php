<?php

namespace App\Support;

/**
 * Catálogos de arranque por tipo de negocio.
 *
 * Es la pieza que más reduce el abandono en el alta: el salón entra y ya
 * tiene sus servicios con duraciones y precios orientativos, en vez de
 * una pantalla vacía. Todo es editable después.
 *
 * Precios orientativos de Canarias, impuesto IGIC 7% incluido.
 */
class PlantillasCatalogo
{
    public const TIPOS = [
        'PELUQUERIA' => 'Peluquería',
        'BARBERIA'   => 'Barbería',
        'ESTETICA'   => 'Estética',
        'UNAS'       => 'Uñas',
        'SPA'        => 'Spa y masajes',
        'MIXTO'      => 'Centro mixto',
    ];

    /**
     * Cada servicio: [nombre, duración, pausa, tiempo final, precio]
     * La pausa es el hueco en que el profesional queda libre (tinte, mechas).
     */
    public static function para(string $tipo): array
    {
        return match ($tipo) {
            'BARBERIA' => self::barberia(),
            'ESTETICA' => self::estetica(),
            'UNAS'     => self::unas(),
            'SPA'      => self::spa(),
            'MIXTO'    => array_merge(self::peluqueria(), self::estetica(), self::unas()),
            default    => self::peluqueria(),
        };
    }

    protected static function peluqueria(): array
    {
        return [
            ['familia' => 'Corte y peinado', 'color' => '#6366f1', 'servicios' => [
                ['Corte de señora',            45,  0,  0, 22.00],
                ['Corte de caballero',         30,  0,  0, 15.00],
                ['Corte infantil',             30,  0,  0, 12.00],
                ['Lavar y peinar',             30,  0,  0, 15.00],
                ['Lavar, cortar y peinar',     60,  0,  0, 30.00],
                ['Recogido',                   60,  0,  0, 45.00],
                ['Peinado de novia',           90,  0,  0, 90.00],
            ]],
            ['familia' => 'Color', 'color' => '#ec4899', 'servicios' => [
                ['Tinte raíz',                 20, 30, 20, 35.00],
                ['Tinte completo',             30, 35, 25, 48.00],
                ['Mechas babylights',          60, 30, 30, 85.00],
                ['Balayage',                   75, 30, 35, 110.00],
                ['Baño de color',              20, 20, 15, 28.00],
                ['Decoloración',               45, 40, 30, 70.00],
            ]],
            ['familia' => 'Tratamientos', 'color' => '#10b981', 'servicios' => [
                ['Hidratación profunda',       30, 15, 10, 30.00],
                ['Keratina',                   90, 30, 30, 130.00],
                ['Alisado permanente',        120, 30, 30, 150.00],
                ['Permanente',                 60, 30, 20, 65.00],
                ['Tratamiento anticaída',      30,  0, 10, 35.00],
            ]],
            ['familia' => 'Productos', 'tipo' => 'PRODUCTO', 'color' => '#f59e0b', 'productos' => [
                ['Champú profesional 300ml',   18.00],
                ['Acondicionador 300ml',       17.00],
                ['Mascarilla capilar 250ml',   22.00],
                ['Aceite de argán 100ml',      24.00],
                ['Laca fijadora 400ml',        14.00],
                ['Cera moldeadora 75ml',       13.00],
            ]],
        ];
    }

    protected static function barberia(): array
    {
        return [
            ['familia' => 'Corte', 'color' => '#0ea5e9', 'servicios' => [
                ['Corte clásico',              30, 0, 0, 15.00],
                ['Corte con degradado',        40, 0, 0, 18.00],
                ['Corte a navaja',             45, 0, 0, 22.00],
                ['Corte infantil',             25, 0, 0, 12.00],
                ['Rapado completo',            20, 0, 0, 10.00],
            ]],
            ['familia' => 'Barba y afeitado', 'color' => '#f97316', 'servicios' => [
                ['Arreglo de barba',           20, 0, 0, 12.00],
                ['Afeitado clásico a navaja',  30, 0, 0, 18.00],
                ['Perfilado de barba',         15, 0, 0, 9.00],
                ['Corte + barba',              50, 0, 0, 25.00],
                ['Tinte de barba',             20, 15, 10, 20.00],
            ]],
            ['familia' => 'Extras', 'color' => '#8b5cf6', 'servicios' => [
                ['Cejas',                      10, 0, 0, 6.00],
                ['Mascarilla facial',          20, 0, 0, 15.00],
                ['Ritual completo',            75, 0, 0, 45.00],
            ]],
            ['familia' => 'Productos', 'tipo' => 'PRODUCTO', 'color' => '#f59e0b', 'productos' => [
                ['Aceite para barba 50ml',     19.00],
                ['Bálsamo para barba 100ml',   17.00],
                ['Pomada fijadora 100ml',      16.00],
                ['Champú para barba 200ml',    15.00],
            ]],
        ];
    }

    protected static function estetica(): array
    {
        return [
            ['familia' => 'Facial', 'color' => '#14b8a6', 'servicios' => [
                ['Limpieza facial básica',     45, 0, 0, 35.00],
                ['Limpieza facial profunda',   75, 0, 0, 55.00],
                ['Peeling químico',            50, 0, 0, 65.00],
                ['Radiofrecuencia facial',     60, 0, 0, 70.00],
                ['Microdermoabrasión',         60, 0, 0, 60.00],
            ]],
            ['familia' => 'Depilación', 'color' => '#f43f5e', 'servicios' => [
                ['Cejas',                      15, 0, 0, 8.00],
                ['Labio superior',             10, 0, 0, 6.00],
                ['Axilas',                     15, 0, 0, 10.00],
                ['Medias piernas',             30, 0, 0, 18.00],
                ['Piernas completas',          45, 0, 0, 28.00],
                ['Ingles',                     20, 0, 0, 15.00],
                ['Láser sesión zona pequeña',  20, 0, 0, 30.00],
                ['Láser sesión zona grande',   45, 0, 0, 70.00],
            ]],
            ['familia' => 'Corporal', 'color' => '#a855f7', 'servicios' => [
                ['Presoterapia',               45, 0, 0, 35.00],
                ['Masaje reductor',            60, 0, 0, 45.00],
                ['Tratamiento anticelulítico', 60, 0, 0, 55.00],
            ]],
            ['familia' => 'Pestañas y cejas', 'color' => '#0891b2', 'servicios' => [
                ['Extensiones pelo a pelo',   120, 0, 0, 70.00],
                ['Relleno de pestañas',        75, 0, 0, 45.00],
                ['Lifting de pestañas',        60, 0, 0, 45.00],
                ['Diseño y tinte de cejas',    30, 0, 0, 20.00],
                ['Laminado de cejas',          45, 0, 0, 35.00],
            ]],
        ];
    }

    protected static function unas(): array
    {
        return [
            ['familia' => 'Manicura', 'color' => '#d946ef', 'servicios' => [
                ['Manicura básica',            30, 0, 0, 15.00],
                ['Manicura con esmaltado',     45, 0, 0, 20.00],
                ['Manicura semipermanente',    60, 0, 0, 25.00],
                ['Uñas de gel',                90, 0, 0, 40.00],
                ['Uñas acrílicas',            105, 0, 0, 45.00],
                ['Relleno de gel',             75, 0, 0, 32.00],
                ['Retirada de esmaltado',      20, 0, 0, 10.00],
            ]],
            ['familia' => 'Pedicura', 'color' => '#06b6d4', 'servicios' => [
                ['Pedicura básica',            45, 0, 0, 22.00],
                ['Pedicura con esmaltado',     60, 0, 0, 28.00],
                ['Pedicura semipermanente',    75, 0, 0, 33.00],
                ['Pedicura spa',               75, 0, 0, 38.00],
            ]],
            ['familia' => 'Decoración', 'color' => '#eab308', 'servicios' => [
                ['Nail art por uña',           10, 0, 0, 3.00],
                ['Francesa',                   15, 0, 0, 6.00],
                ['Diseño completo',            30, 0, 0, 15.00],
            ]],
        ];
    }

    protected static function spa(): array
    {
        return [
            ['familia' => 'Masajes', 'color' => '#84cc16', 'servicios' => [
                ['Masaje relajante 30 min',    30, 0, 10, 30.00],
                ['Masaje relajante 60 min',    60, 0, 10, 50.00],
                ['Masaje descontracturante',   60, 0, 10, 55.00],
                ['Masaje deportivo',           60, 0, 10, 55.00],
                ['Drenaje linfático',          75, 0, 10, 60.00],
                ['Masaje con piedras',         75, 0, 15, 65.00],
            ]],
            ['familia' => 'Rituales', 'color' => '#22c55e', 'servicios' => [
                ['Ritual de chocolate',        90, 0, 15, 80.00],
                ['Envoltura de algas',         75, 0, 15, 70.00],
                ['Exfoliación corporal',       45, 0, 10, 40.00],
                ['Circuito spa 2 personas',   120, 0, 15, 110.00],
            ]],
        ];
    }
}
