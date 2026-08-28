<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

/**
 * El test mas importante de todo el proyecto.
 *
 * Con una base de datos por empresa, un fallo de aislamiento significa
 * ensenar la agenda y los clientes de un salon a otro. Debe ejecutarse
 * en cada despliegue y no debe borrarse nunca.
 *
 * OJO: no usa RefreshDatabase porque necesita crear bases de datos reales.
 * Requiere que el usuario MySQL tenga permisos CREATE y DROP.
 */
class AislamientoEmpresasTest extends TestCase
{
    protected ?Empresa $empresaA = null;
    protected ?Empresa $empresaB = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresaA = Empresa::create([
            'slug'             => 'test-a-' . uniqid(),
            'nombre_comercial' => 'Salon A',
            'email'            => 'a@test.local',
        ]);

        $this->empresaB = Empresa::create([
            'slug'             => 'test-b-' . uniqid(),
            'nombre_comercial' => 'Salon B',
            'email'            => 'b@test.local',
        ]);
    }

    protected function tearDown(): void
    {
        // El borrado de la base de datos NO es automatico (ver
        // TenancyServiceProvider): aqui se hace a mano igual que
        // en el comando climacopos:purgar-empresa
        foreach ([$this->empresaA, $this->empresaB] as $empresa) {
            if (! $empresa) {
                continue;
            }

            if (tenancy()->initialized) {
                tenancy()->end();
            }

            try {
                (new DeleteDatabase($empresa))->handle();
            } catch (\Throwable) {
                // la base ya no existia
            }

            $empresa->domains()->delete();
            $empresa->forceDelete();
        }

        parent::tearDown();
    }

    public function test_cada_empresa_tiene_su_propia_base_de_datos(): void
    {
        $this->assertNotSame(
            $this->empresaA->tenancy_db_name,
            $this->empresaB->tenancy_db_name,
            'Dos empresas comparten nombre de base de datos'
        );

        $this->assertSame(
            'climacopos_emp_' . $this->empresaA->id,
            $this->empresaA->tenancy_db_name
        );
    }

    public function test_la_clave_primaria_es_numerica_y_autoincremental(): void
    {
        $this->assertIsInt($this->empresaA->id);
        $this->assertGreaterThan(0, $this->empresaA->id);
        $this->assertNotEmpty($this->empresaA->uuid);
    }

    public function test_los_datos_de_una_empresa_no_son_visibles_desde_otra(): void
    {
        tenancy()->initialize($this->empresaA);
        DB::table('config')->insert(['clave' => 'nombre_negocio', 'valor' => 'SECRETO DE A']);
        DB::table('terminales')->insert(['nombre' => 'Caja de A', 'codigo' => 'A01']);
        tenancy()->end();

        tenancy()->initialize($this->empresaB);

        $this->assertSame(0, DB::table('terminales')->count(),
            'La empresa B ve terminales de la empresa A');

        $this->assertNull(DB::table('config')->where('clave', 'nombre_negocio')->first(),
            'La empresa B ve la configuracion de la empresa A');

        DB::table('config')->insert(['clave' => 'nombre_negocio', 'valor' => 'SECRETO DE B']);
        tenancy()->end();

        tenancy()->initialize($this->empresaA);
        $this->assertSame('SECRETO DE A',
            DB::table('config')->where('clave', 'nombre_negocio')->value('valor'));
        tenancy()->end();
    }

    public function test_fuera_de_contexto_no_hay_acceso_a_tablas_de_empresa(): void
    {
        $this->assertFalse(tenancy()->initialized);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('terminales')->count();
    }

    public function test_la_conexion_vuelve_a_la_central_al_terminar(): void
    {
        $central = DB::connection()->getDatabaseName();

        tenancy()->initialize($this->empresaA);
        $this->assertSame($this->empresaA->tenancy_db_name, DB::connection()->getDatabaseName());
        tenancy()->end();

        $this->assertSame($central, DB::connection()->getDatabaseName(),
            'La conexion se ha quedado apuntando a la base de una empresa');
    }

    /**
     * Regresion: el borrado logico NO debe destruir la base de datos.
     * La plataforma promete 90 dias de retencion.
     */
    public function test_la_baja_logica_conserva_la_base_de_datos(): void
    {
        $nombreBd = $this->empresaA->tenancy_db_name;

        $this->empresaA->delete();

        $this->assertTrue($this->empresaA->trashed());

        $existe = DB::selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$nombreBd]
        );

        $this->assertNotNull($existe,
            'La baja logica ha borrado la base de datos de la empresa');
    }
}
