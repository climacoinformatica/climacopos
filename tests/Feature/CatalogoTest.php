<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Support\PlantillasCatalogo;
use Database\Seeders\Tenant\CatalogoPlantillaSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    protected ?Empresa $empresa = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-cat-' . uniqid(),
            'nombre_comercial' => 'Salon Catalogo',
            'email'            => 'c@test.local',
            'tipo_negocio'     => 'PELUQUERIA',
            'regimen_fiscal'   => 'IGIC',
        ]);

        tenancy()->initialize($this->empresa);
        (new PerfilesSeeder())->run();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->empresa) {
            try {
                (new DeleteDatabase($this->empresa))->handle();
            } catch (\Throwable) {
            }

            $this->empresa->domains()->delete();
            $this->empresa->forceDelete();
        }

        parent::tearDown();
    }

    protected function crearServicio(array $extra = []): Articulo
    {
        $familia = Familia::firstOrCreate(['nombre' => 'Pruebas'], ['tipo' => 'SERVICIO']);

        return Articulo::create(array_merge([
            'familia_id'   => $familia->id,
            'tipo'         => 'SERVICIO',
            'nombre'       => 'Servicio de prueba',
            'precio'       => 107.00,
            'impuesto_pct' => 7.00,
            'duracion_min' => 30,
        ], $extra));
    }

    // ------------------------------------------------------------------
    // Precios: el precio guardado lleva impuesto incluido
    // ------------------------------------------------------------------

    public function test_la_base_imponible_se_calcula_desde_el_precio_con_impuesto(): void
    {
        $articulo = $this->crearServicio(['precio' => 107.00, 'impuesto_pct' => 7.00]);

        $this->assertEqualsWithDelta(100.00, $articulo->baseImponible(), 0.01);
        $this->assertEqualsWithDelta(7.00, $articulo->cuotaImpuesto(), 0.01);
    }

    public function test_la_base_imponible_funciona_con_iva_peninsular(): void
    {
        $articulo = $this->crearServicio(['precio' => 121.00, 'impuesto_pct' => 21.00]);

        $this->assertEqualsWithDelta(100.00, $articulo->baseImponible(), 0.01);
        $this->assertEqualsWithDelta(21.00, $articulo->cuotaImpuesto(), 0.01);
    }

    public function test_base_mas_cuota_siempre_suman_el_precio(): void
    {
        foreach ([[22.00, 7.0], [15.50, 3.0], [48.90, 7.0], [110.00, 21.0]] as [$precio, $impuesto]) {
            $articulo = $this->crearServicio(['precio' => $precio, 'impuesto_pct' => $impuesto]);

            $this->assertEqualsWithDelta(
                $precio,
                $articulo->baseImponible() + $articulo->cuotaImpuesto(),
                0.01,
                "Descuadre con precio {$precio} e impuesto {$impuesto}%"
            );
        }
    }

    public function test_el_margen_se_calcula_sobre_la_base_no_sobre_el_precio(): void
    {
        // Base 100, coste 40 -> margen 60%
        $articulo = $this->crearServicio(['precio' => 107.00, 'impuesto_pct' => 7.00, 'coste' => 40.00]);

        $this->assertEqualsWithDelta(60.00, $articulo->margen(), 0.1);
    }

    public function test_sin_coste_no_hay_margen(): void
    {
        $this->assertNull($this->crearServicio()->margen());
    }

    // ------------------------------------------------------------------
    // Duración y pausa
    // ------------------------------------------------------------------

    public function test_la_duracion_total_incluye_pausa_y_tiempo_final(): void
    {
        // Tinte: 20' aplicando + 30' esperando + 15' lavando
        $tinte = $this->crearServicio([
            'duracion_min'     => 20,
            'tiempo_pausa_min' => 30,
            'tiempo_final_min' => 15,
        ]);

        $this->assertSame(65, $tinte->duracionTotal());
        $this->assertSame(20, $tinte->duracionPara());
        $this->assertTrue($tinte->tienePausa());
    }

    public function test_un_profesional_puede_tener_su_propia_duracion_y_precio(): void
    {
        $servicio = $this->crearServicio(['precio' => 30.00, 'duracion_min' => 45]);

        $usuario = Usuario::create([
            'nombre'         => 'Estilista senior',
            'perfil_id'      => Perfil::where('clave', 'profesional')->value('id'),
            'es_profesional' => true,
            'pin'            => '1234',
        ]);

        $servicio->profesionales()->attach($usuario->id, ['precio' => 45.00, 'duracion_min' => 60]);
        $servicio->load('profesionales');

        $this->assertEqualsWithDelta(45.00, $servicio->precioPara($usuario), 0.01);
        $this->assertSame(60, $servicio->duracionPara($usuario));

        // Sin profesional, la tarifa general
        $this->assertEqualsWithDelta(30.00, $servicio->precioPara(null), 0.01);
        $this->assertSame(45, $servicio->duracionPara(null));
    }

    // ------------------------------------------------------------------
    // Fianzas
    // ------------------------------------------------------------------

    public function test_la_fianza_por_porcentaje_se_calcula_sobre_el_precio(): void
    {
        $articulo = $this->crearServicio([
            'precio'        => 80.00,
            'politica_pago' => 'FIANZA',
            'fianza_pct'    => 25.00,
        ]);

        $this->assertEqualsWithDelta(20.00, $articulo->importeFianza(), 0.01);
    }

    public function test_la_fianza_por_importe_fijo_manda_sobre_el_porcentaje(): void
    {
        $articulo = $this->crearServicio([
            'precio'         => 80.00,
            'politica_pago'  => 'FIANZA',
            'fianza_importe' => 15.00,
            'fianza_pct'     => 50.00,
        ]);

        $this->assertEqualsWithDelta(15.00, $articulo->importeFianza(), 0.01);
    }

    public function test_pago_total_reserva_el_precio_completo(): void
    {
        $articulo = $this->crearServicio(['precio' => 80.00, 'politica_pago' => 'TOTAL']);

        $this->assertEqualsWithDelta(80.00, $articulo->importeFianza(), 0.01);
    }

    public function test_sin_politica_de_pago_no_se_cobra_nada(): void
    {
        $this->assertEqualsWithDelta(0.00, $this->crearServicio()->importeFianza(), 0.01);
    }

    // ------------------------------------------------------------------
    // Plantillas
    // ------------------------------------------------------------------

    public function test_la_plantilla_precarga_el_catalogo_de_peluqueria(): void
    {
        (new CatalogoPlantillaSeeder())->run();

        $this->assertGreaterThan(0, Familia::count());
        $this->assertGreaterThan(10, Articulo::count());

        $tinte = Articulo::where('nombre', 'like', 'Tinte%')->first();
        $this->assertNotNull($tinte, 'La plantilla de peluquería debe incluir tintes');
        $this->assertGreaterThan(0, $tinte->tiempo_pausa_min, 'Los tintes deben llevar pausa');
    }

    public function test_la_plantilla_no_duplica_si_ya_hay_catalogo(): void
    {
        (new CatalogoPlantillaSeeder())->run();
        $antes = Articulo::count();

        (new CatalogoPlantillaSeeder())->run();

        $this->assertSame($antes, Articulo::count());
    }

    public function test_hay_plantilla_para_todos_los_tipos_de_negocio(): void
    {
        foreach (array_keys(PlantillasCatalogo::TIPOS) as $tipo) {
            $this->assertNotEmpty(PlantillasCatalogo::para($tipo), "Falta plantilla para {$tipo}");
        }
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public function test_solo_los_servicios_activos_son_reservables_online(): void
    {
        $this->crearServicio(['nombre' => 'Visible', 'permite_reserva_online' => true]);
        $this->crearServicio(['nombre' => 'Oculto', 'permite_reserva_online' => false]);
        $this->crearServicio(['nombre' => 'Inactivo', 'permite_reserva_online' => true, 'activo' => false]);
        $this->crearServicio(['nombre' => 'Champu', 'tipo' => 'PRODUCTO', 'permite_reserva_online' => true]);

        $reservables = Articulo::reservablesOnline()->pluck('nombre')->all();

        $this->assertSame(['Visible'], $reservables);
    }

    public function test_el_stock_bajo_minimo_se_detecta(): void
    {
        $this->crearServicio(['nombre' => 'Bajo', 'tipo' => 'PRODUCTO', 'control_stock' => true, 'stock' => 1, 'stock_min' => 3]);
        $this->crearServicio(['nombre' => 'Sobrado', 'tipo' => 'PRODUCTO', 'control_stock' => true, 'stock' => 10, 'stock_min' => 3]);
        $this->crearServicio(['nombre' => 'Sin control', 'tipo' => 'PRODUCTO', 'control_stock' => false, 'stock' => 0, 'stock_min' => 5]);

        $this->assertSame(['Bajo'], Articulo::bajoMinimo()->pluck('nombre')->all());
    }

    public function test_no_se_borra_una_familia_con_articulos(): void
    {
        $articulo = $this->crearServicio();
        $familia  = $articulo->familia;

        $this->assertFalse($familia->puedeBorrarse());

        $articulo->forceDelete();

        $this->assertTrue($familia->fresh()->puedeBorrarse());
    }

    public function test_el_borrado_de_articulo_es_logico(): void
    {
        $articulo = $this->crearServicio();
        $id = $articulo->id;

        $articulo->delete();

        $this->assertNull(Articulo::find($id));
        $this->assertNotNull(Articulo::withTrashed()->find($id),
            'Los tickets antiguos deben poder seguir apuntando al artículo');
    }
}
