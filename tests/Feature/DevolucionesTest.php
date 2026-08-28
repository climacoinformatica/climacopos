<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Models\VerifactuRegistro;
use App\Services\GestorCierre;
use App\Services\GestorDevoluciones;
use App\Services\GestorTickets;
use App\Services\Verifactu\GeneradorXml;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class DevolucionesTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorTickets $tpv;
    protected GestorDevoluciones $devoluciones;
    protected Usuario $marta;
    protected Usuario $aprendiz;
    protected Articulo $corte;
    protected Articulo $champu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-dev-' . uniqid(),
            'nombre_comercial' => 'Salón Devoluciones',
            'razon_social'     => 'Salón Devoluciones SL',
            'nif'              => 'B11223344',
            'email'            => 'dev@test.local',
            'verifactu_activo' => true,
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->tpv = new GestorTickets();
        $this->devoluciones = new GestorDevoluciones();

        $this->marta = Usuario::create([
            'nombre'    => 'Marta',
            'perfil_id' => Perfil::where('clave', 'encargado')->value('id'),
            'pin'       => '1234',
        ]);

        $this->aprendiz = Usuario::create([
            'nombre'       => 'Aprendiz',
            'perfil_id'    => Perfil::where('clave', 'formacion')->value('id'),
            'en_formacion' => true,
            'pin'          => '5678',
        ]);

        $familia = Familia::create(['nombre' => 'General', 'tipo' => 'AMBOS']);

        $this->corte = Articulo::create([
            'familia_id' => $familia->id, 'tipo' => 'SERVICIO', 'nombre' => 'Corte',
            'precio' => 22.00, 'impuesto_pct' => 7.00,
        ]);

        $this->champu = Articulo::create([
            'familia_id' => $familia->id, 'tipo' => 'PRODUCTO', 'nombre' => 'Champú',
            'precio' => 18.00, 'impuesto_pct' => 7.00,
            'control_stock' => true, 'stock' => 10,
        ]);
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

    /** Ticket cobrado y con el día ya cerrado. */
    protected function ventaCerrada(): Ticket
    {
        $ticket = $this->tpv->abrir($this->marta);
        $this->tpv->anadirLinea($ticket, $this->corte);
        $this->tpv->anadirLinea($ticket, $this->champu, 2);
        $this->tpv->cobrar($ticket, 'EFECTIVO', 58.00);   // 22 + 36

        (new GestorCierre())->cerrar($this->marta, 58.00);

        return $ticket->fresh();
    }

    // ------------------------------------------------------------------
    // Cuándo procede cada cosa
    // ------------------------------------------------------------------

    public function test_un_ticket_cerrado_ya_no_se_puede_anular(): void
    {
        $ticket = $this->ventaCerrada();

        $this->assertFalse($ticket->esAnulable());
        $this->assertTrue($ticket->esDevolvible(),
            'Lo que no se puede anular, se rectifica');
    }

    public function test_no_se_rectifica_un_documento_de_formacion(): void
    {
        $practica = $this->tpv->abrir($this->aprendiz);
        $this->tpv->anadirLinea($practica, $this->corte);
        $this->tpv->cobrar($practica, 'EFECTIVO', 22.00);

        $ticket = Ticket::soloFormacion()->find($practica->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no es una factura');

        $this->devoluciones->devolverTodo($ticket, 'Prueba');
    }

    public function test_no_se_rectifica_una_rectificativa(): void
    {
        $rectificativa = $this->devoluciones->devolverTodo($this->ventaCerrada(), 'Error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se puede rectificar una rectificativa');

        $this->devoluciones->devolverTodo($rectificativa, 'Otra vez');
    }

    public function test_no_se_devuelve_un_ticket_abierto(): void
    {
        $ticket = $this->tpv->abrir($this->marta);
        $this->tpv->anadirLinea($ticket, $this->corte);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sigue abierto');

        $this->devoluciones->devolverTodo($ticket->fresh(), 'Nada');
    }

    // ------------------------------------------------------------------
    // La rectificativa
    // ------------------------------------------------------------------

    public function test_la_rectificativa_usa_serie_propia(): void
    {
        $rectificativa = $this->devoluciones->devolverTodo($this->ventaCerrada(), 'Cliente insatisfecha');

        $this->assertSame('R', $rectificativa->serie);
        $this->assertSame(1, $rectificativa->numero);
        $this->assertSame('RECTIFICATIVA', $rectificativa->tipo_documento);
        $this->assertSame('R5', $rectificativa->tipo_rectificativa);
    }

    public function test_la_rectificativa_no_consume_numeracion_de_facturas(): void
    {
        $this->devoluciones->devolverTodo($this->ventaCerrada(), 'Motivo');

        // La siguiente venta real sigue la numeración normal
        $siguiente = $this->tpv->abrir($this->marta);

        $this->assertSame('A', $siguiente->serie);
        $this->assertSame(2, $siguiente->numero);
    }

    public function test_los_importes_van_en_negativo(): void
    {
        $rectificativa = $this->devoluciones->devolverTodo($this->ventaCerrada(), 'Motivo');

        $this->assertEqualsWithDelta(-58.00, (float) $rectificativa->total, 0.01);
        $this->assertLessThan(0, (float) $rectificativa->base);
        $this->assertLessThan(0, (float) $rectificativa->impuesto);
    }

    public function test_el_original_no_se_toca(): void
    {
        $original = $this->ventaCerrada();
        $this->devoluciones->devolverTodo($original, 'Motivo');

        $original = $original->fresh();

        $this->assertSame('COBRADO', $original->estado);
        $this->assertEqualsWithDelta(58.00, (float) $original->total, 0.01,
            'La factura original se queda exactamente como estaba');
        $this->assertNotNull($original->cierre_id, 'Y sigue en su cierre');
    }

    public function test_la_rectificativa_apunta_al_original(): void
    {
        $original = $this->ventaCerrada();
        $rectificativa = $this->devoluciones->devolverTodo($original, 'Motivo');

        $this->assertSame($original->id, $rectificativa->rectifica_ticket_id);
        $this->assertSame($original->id, $rectificativa->rectificaA->id);
        $this->assertCount(1, $original->fresh()->rectificativas);
    }

    // ------------------------------------------------------------------
    // Devolución parcial
    // ------------------------------------------------------------------

    public function test_se_puede_devolver_solo_una_parte(): void
    {
        $original = $this->ventaCerrada();
        $lineaChampu = $original->lineas()->where('descripcion', 'Champú')->first();

        // Solo un champú de los dos
        $rectificativa = $this->devoluciones->devolver(
            $original, [$lineaChampu->id => 1], 'Uno defectuoso',
        );

        $this->assertEqualsWithDelta(-18.00, (float) $rectificativa->total, 0.01);
    }

    public function test_no_se_puede_devolver_mas_de_lo_vendido(): void
    {
        $original = $this->ventaCerrada();
        $lineaChampu = $original->lineas()->where('descripcion', 'Champú')->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('solo quedan');

        $this->devoluciones->devolver($original, [$lineaChampu->id => 5], 'Motivo');
    }

    public function test_no_se_puede_devolver_dos_veces_lo_mismo(): void
    {
        $original = $this->ventaCerrada();
        $lineaChampu = $original->lineas()->where('descripcion', 'Champú')->first();

        $this->devoluciones->devolver($original, [$lineaChampu->id => 2], 'Primera');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('solo quedan');

        $this->devoluciones->devolver($original->fresh(), [$lineaChampu->id => 1], 'Segunda');
    }

    public function test_se_puede_devolver_en_dos_veces_hasta_completar(): void
    {
        $original = $this->ventaCerrada();
        $lineaChampu = $original->lineas()->where('descripcion', 'Champú')->first();

        $this->devoluciones->devolver($original, [$lineaChampu->id => 1], 'Primera');
        $segunda = $this->devoluciones->devolver($original->fresh(), [$lineaChampu->id => 1], 'Segunda');

        $this->assertEqualsWithDelta(-18.00, (float) $segunda->total, 0.01);

        $disponible = $this->devoluciones->disponible($original->fresh());
        $this->assertEqualsWithDelta(0.0, $disponible[$lineaChampu->id]['disponible'], 0.001);
    }

    // ------------------------------------------------------------------
    // Stock y dinero
    // ------------------------------------------------------------------

    public function test_la_devolucion_repone_el_stock(): void
    {
        $original = $this->ventaCerrada();

        // 10 iniciales − 2 vendidos = 8
        $this->assertEqualsWithDelta(8.0, (float) $this->champu->fresh()->stock, 0.001);

        $this->devoluciones->devolverTodo($original, 'Motivo');

        $this->assertEqualsWithDelta(10.0, (float) $this->champu->fresh()->stock, 0.001);
    }

    public function test_el_reembolso_cierra_la_rectificativa(): void
    {
        $rectificativa = $this->devoluciones->devolverTodo($this->ventaCerrada(), 'Motivo');

        $this->devoluciones->reembolsar($rectificativa, 'EFECTIVO');

        $this->assertSame('COBRADO', $rectificativa->fresh()->estado);
        $this->assertEqualsWithDelta(-58.00,
            (float) $rectificativa->cobros()->sum('importe'), 0.01);
    }

    public function test_el_ticket_original_sabe_cuanto_se_devolvio(): void
    {
        $original = $this->ventaCerrada();
        $rectificativa = $this->devoluciones->devolverTodo($original, 'Motivo');
        $this->devoluciones->reembolsar($rectificativa, 'EFECTIVO');

        $original = $original->fresh();

        $this->assertTrue($original->tieneDevoluciones());
        $this->assertEqualsWithDelta(58.00, $original->importeDevuelto(), 0.01);
        $this->assertEqualsWithDelta(0.00, $original->importeNeto(), 0.01);
    }

    public function test_una_devolucion_parcial_deja_neto_positivo(): void
    {
        $original = $this->ventaCerrada();
        $lineaCorte = $original->lineas()->where('descripcion', 'Corte')->first();

        $rectificativa = $this->devoluciones->devolver($original, [$lineaCorte->id => 1], 'Motivo');
        $this->devoluciones->reembolsar($rectificativa, 'EFECTIVO');

        // 58 vendidos − 22 devueltos = 36
        $this->assertEqualsWithDelta(36.00, $original->fresh()->importeNeto(), 0.01);
    }

    // ------------------------------------------------------------------
    // VERI*FACTU
    // ------------------------------------------------------------------

    public function test_la_rectificativa_genera_su_registro_fiscal(): void
    {
        $original = $this->ventaCerrada();
        $rectificativa = $this->devoluciones->devolverTodo($original, 'Motivo');
        $this->devoluciones->reembolsar($rectificativa, 'EFECTIVO');

        $registro = VerifactuRegistro::where('ticket_id', $rectificativa->id)->first();

        $this->assertNotNull($registro);
        $this->assertSame('R5', $registro->tipo_factura,
            'Un ticket es factura simplificada, así que se rectifica con R5');
    }

    public function test_el_xml_de_la_rectificativa_referencia_a_la_original(): void
    {
        $original = $this->ventaCerrada();
        $rectificativa = $this->devoluciones->devolverTodo($original, 'Motivo');
        $this->devoluciones->reembolsar($rectificativa, 'EFECTIVO');

        $registro = VerifactuRegistro::where('ticket_id', $rectificativa->id)->first();
        $xml = (new GeneradorXml())->registro($registro);

        $this->assertStringContainsString('FacturasRectificadas', $xml);
        $this->assertStringContainsString($original->referencia(), $xml);
        $this->assertStringContainsString('TipoRectificativa', $xml);
    }

    public function test_la_rectificativa_sigue_la_cadena_de_huellas(): void
    {
        $original = $this->ventaCerrada();
        $registroOriginal = VerifactuRegistro::where('ticket_id', $original->id)->first();

        $rectificativa = $this->devoluciones->devolverTodo($original, 'Motivo');
        $this->devoluciones->reembolsar($rectificativa, 'EFECTIVO');

        $registroRect = VerifactuRegistro::where('ticket_id', $rectificativa->id)->first();

        $this->assertSame($registroOriginal->huella, $registroRect->huella_anterior,
            'Una rectificativa encadena igual que cualquier otro documento');
    }
}
