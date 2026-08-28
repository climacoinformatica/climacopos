<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\BonoPlantilla;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Services\GestorBonos;
use App\Services\GestorTickets;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

/**
 * Se prueba la lógica del controlador llamando a sus métodos, sin pasar
 * por HTTP: las rutas del panel exigen terminal vinculado y sesión de
 * salón, y montar todo eso en cada test añadiría ruido sin comprobar
 * nada más de lo que ya cubren otros.
 */
class ClienteTpvTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorTickets $tpv;
    protected Usuario $marta;
    protected Articulo $manicura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-cli-' . uniqid(),
            'nombre_comercial' => 'Salón Clientes',
            'email'            => 'cli@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->tpv = new GestorTickets();

        $this->marta = Usuario::create([
            'nombre'    => 'Marta',
            'perfil_id' => Perfil::where('clave', 'encargado')->value('id'),
            'pin'       => '1234',
        ]);

        $familia = Familia::create(['nombre' => 'Uñas', 'tipo' => 'SERVICIO']);

        $this->manicura = Articulo::create([
            'familia_id' => $familia->id, 'tipo' => 'SERVICIO', 'nombre' => 'Manicura',
            'precio' => 15.00, 'impuesto_pct' => 7.00,
        ]);

        Cliente::create(['nombre' => 'María', 'apellidos' => 'López', 'telefono' => '600111222']);
        Cliente::create(['nombre' => 'Lucía', 'apellidos' => 'Pérez', 'telefono' => '600333444',
                         'email' => 'lucia@ejemplo.com']);
        Cliente::create(['nombre' => 'Ana', 'apellidos' => 'García', 'telefono' => '600555666',
                         'bloqueado' => true]);
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

    protected function buscar(string $texto): array
    {
        $controlador = new \App\Http\Controllers\Panel\TpvController();

        $peticion = \Illuminate\Http\Request::create('/panel/tpv/clientes', 'GET', ['q' => $texto]);

        return json_decode($controlador->buscarClientes($peticion)->getContent(), true)['clientes'];
    }

    // ------------------------------------------------------------------
    // Búsqueda
    // ------------------------------------------------------------------

    public function test_se_busca_por_nombre(): void
    {
        $resultados = $this->buscar('mar');

        $this->assertCount(1, $resultados);
        $this->assertSame('María López', $resultados[0]['nombre']);
    }

    public function test_se_busca_por_apellido(): void
    {
        $this->assertCount(1, $this->buscar('pérez'));
    }

    public function test_se_busca_por_telefono_parcial(): void
    {
        $resultados = $this->buscar('333444');

        $this->assertCount(1, $resultados);
        $this->assertSame('Lucía Pérez', $resultados[0]['nombre']);
    }

    public function test_se_busca_por_email(): void
    {
        $this->assertCount(1, $this->buscar('lucia@'));
    }

    public function test_se_busca_por_nombre_y_apellido_juntos(): void
    {
        $resultados = $this->buscar('maría lópez');

        $this->assertCount(1, $resultados,
            'Escribir el nombre completo es lo natural y tiene que funcionar');
    }

    public function test_los_clientes_bloqueados_no_aparecen(): void
    {
        $this->assertCount(0, $this->buscar('ana'),
            'Un cliente bloqueado no debe poder asignarse a un ticket');
    }

    public function test_con_menos_de_dos_letras_no_se_busca(): void
    {
        $this->assertCount(0, $this->buscar('m'),
            'Buscar con una letra devolvería medio fichero y no ayuda a nadie');
    }

    // ------------------------------------------------------------------
    // Lo que se muestra de cada cliente
    // ------------------------------------------------------------------

    public function test_el_resultado_incluye_el_saldo_del_monedero(): void
    {
        $lucia = Cliente::where('nombre', 'Lucía')->first();

        (new GestorBonos())->recargarMonedero($lucia, 40.00);

        $resultados = $this->buscar('lucía');

        $this->assertEqualsWithDelta(40.00, $resultados[0]['saldo'], 0.01);
    }

    public function test_el_resultado_incluye_los_bonos_activos(): void
    {
        $lucia = Cliente::where('nombre', 'Lucía')->first();

        $plantilla = BonoPlantilla::create([
            'nombre'       => 'Bono 5 manicuras',
            'modalidad'    => 'SESIONES',
            'precio'       => 60.00,
            'num_sesiones' => 5,
            'articulo_id'  => $this->manicura->id,
        ]);

        (new GestorBonos())->vender($plantilla, $lucia);

        $resultados = $this->buscar('lucía');

        $this->assertCount(1, $resultados[0]['bonos']);
        $this->assertStringContainsString('5 de 5', $resultados[0]['bonos'][0]['resumen']);
    }

    // ------------------------------------------------------------------
    // Alta rápida
    // ------------------------------------------------------------------

    protected function crear(array $datos, $ticket): array
    {
        $controlador = new \App\Http\Controllers\Panel\TpvController();

        $peticion = \Illuminate\Http\Request::create('/x', 'POST', $datos);

        return json_decode($controlador->crearCliente($peticion, $ticket)->getContent(), true);
    }

    public function test_se_puede_crear_una_ficha_solo_con_el_nombre(): void
    {
        $ticket = $this->tpv->abrir($this->marta);

        $resultado = $this->crear(['nombre' => 'Carmen'], $ticket);

        $this->assertTrue($resultado['ok']);
        $this->assertSame('Carmen', $resultado['cliente']['nombre']);
        $this->assertNotNull($ticket->fresh()->cliente_id);
    }

    public function test_un_telefono_repetido_reutiliza_la_ficha(): void
    {
        $ticket = $this->tpv->abrir($this->marta);

        $antes = Cliente::count();

        $resultado = $this->crear(
            ['nombre' => 'María otra vez', 'telefono' => '600111222'],
            $ticket,
        );

        $this->assertSame($antes, Cliente::count(),
            'Duplicar fichas ensucia el fichero para siempre');
        $this->assertNotNull($resultado['aviso']);
        $this->assertSame('María López', $resultado['cliente']['nombre']);
    }

    // ------------------------------------------------------------------
    // Asignación y bonos
    // ------------------------------------------------------------------

    protected function asignar($ticket, ?int $clienteId): array
    {
        $controlador = new \App\Http\Controllers\Panel\TpvController();

        $peticion = \Illuminate\Http\Request::create('/x', 'POST', ['cliente_id' => $clienteId]);

        return json_decode($controlador->cliente($peticion, $ticket)->getContent(), true);
    }

    public function test_asignar_cliente_ofrece_los_bonos_de_lo_ya_tecleado(): void
    {
        $lucia = Cliente::where('nombre', 'Lucía')->first();

        $plantilla = BonoPlantilla::create([
            'nombre'       => 'Bono 5 manicuras',
            'modalidad'    => 'SESIONES',
            'precio'       => 60.00,
            'num_sesiones' => 5,
            'articulo_id'  => $this->manicura->id,
        ]);

        (new GestorBonos())->vender($plantilla, $lucia);

        // Primero se teclea el servicio, después se asigna la ficha:
        // es el orden natural en el mostrador
        $ticket = $this->tpv->abrir($this->marta);
        $this->tpv->anadirLinea($ticket, $this->manicura);

        $resultado = $this->asignar($ticket->fresh(), $lucia->id);

        $this->assertCount(1, $resultado['con_bono'],
            'Si solo se miraran los bonos al añadir la línea, en este orden '
            . 'no se ofrecería ninguno');
        $this->assertSame('Manicura', $resultado['con_bono'][0]['descripcion']);
    }

    public function test_una_linea_ya_pagada_con_bono_no_se_vuelve_a_ofrecer(): void
    {
        $lucia = Cliente::where('nombre', 'Lucía')->first();

        $plantilla = BonoPlantilla::create([
            'nombre'       => 'Bono manicuras',
            'modalidad'    => 'SESIONES',
            'precio'       => 60.00,
            'num_sesiones' => 5,
            'articulo_id'  => $this->manicura->id,
        ]);

        $bono = (new GestorBonos())->vender($plantilla, $lucia);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->manicura);

        (new GestorBonos())->consumir($bono, $linea->fresh(), $this->marta);

        $resultado = $this->asignar($ticket->fresh(), $lucia->id);

        $this->assertCount(0, $resultado['con_bono']);
    }

    public function test_se_puede_quitar_el_cliente_del_ticket(): void
    {
        $lucia = Cliente::where('nombre', 'Lucía')->first();

        $ticket = $this->tpv->abrir($this->marta);
        $this->asignar($ticket, $lucia->id);

        $resultado = $this->asignar($ticket->fresh(), null);

        $this->assertNull($resultado['cliente']);
        $this->assertNull($ticket->fresh()->cliente_id);
    }
}
