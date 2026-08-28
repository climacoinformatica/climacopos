<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\CierreJornada;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\GestorCierre;
use App\Services\GestorTickets;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class TpvTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorTickets $gestor;
    protected Usuario $marta;
    protected Usuario $aprendiz;
    protected Articulo $corte;
    protected Articulo $champu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-tpv-' . uniqid(),
            'nombre_comercial' => 'Salon TPV',
            'email'            => 't@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->gestor = new GestorTickets();

        $this->marta = Usuario::create([
            'nombre'         => 'Marta',
            'perfil_id'      => Perfil::where('clave', 'encargado')->value('id'),
            'es_profesional' => true,
            'pin'            => '1234',
        ]);

        $this->aprendiz = Usuario::create([
            'nombre'         => 'Aprendiz',
            'perfil_id'      => Perfil::where('clave', 'formacion')->value('id'),
            'es_profesional' => true,
            'en_formacion'   => true,
            'pin'            => '5678',
        ]);

        $familia = Familia::create(['nombre' => 'Pruebas', 'tipo' => 'AMBOS']);

        $this->corte = Articulo::create([
            'familia_id'   => $familia->id,
            'tipo'         => 'SERVICIO',
            'nombre'       => 'Corte',
            'precio'       => 22.00,
            'impuesto_pct' => 7.00,
            'duracion_min' => 30,
        ]);

        $this->champu = Articulo::create([
            'familia_id'    => $familia->id,
            'tipo'          => 'PRODUCTO',
            'nombre'        => 'Champú',
            'precio'        => 18.00,
            'impuesto_pct'  => 7.00,
            'control_stock' => true,
            'stock'         => 10,
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

    // ------------------------------------------------------------------
    // Importes
    // ------------------------------------------------------------------

    public function test_el_ticket_calcula_base_e_impuesto_desde_el_precio_final(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $ticket = $ticket->fresh();

        $this->assertEqualsWithDelta(22.00, (float) $ticket->total, 0.01);
        $this->assertEqualsWithDelta(20.56, (float) $ticket->base, 0.01);
        $this->assertEqualsWithDelta(1.44, (float) $ticket->impuesto, 0.01);
    }

    public function test_base_mas_impuesto_cuadran_con_el_total(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte, 3);
        $this->gestor->anadirLinea($ticket, $this->champu, 2);

        $ticket = $ticket->fresh();

        $this->assertEqualsWithDelta(
            (float) $ticket->total,
            (float) $ticket->base + (float) $ticket->impuesto,
            0.01
        );
        $this->assertEqualsWithDelta(102.00, (float) $ticket->total, 0.01);
    }

    public function test_el_descuento_se_aplica_sobre_el_importe(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $linea = $this->gestor->anadirLinea($ticket, $this->corte);

        $this->gestor->aplicarDescuento($ticket, $linea, 50);

        $this->assertEqualsWithDelta(11.00, (float) $ticket->fresh()->total, 0.01);
    }

    public function test_una_invitacion_deja_la_linea_a_cero(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $linea = $this->gestor->anadirLinea($ticket, $this->corte);

        $this->gestor->invitar($ticket, $linea, 'Cliente habitual');

        $ticket = $ticket->fresh();

        $this->assertEqualsWithDelta(0.00, (float) $ticket->total, 0.01);
        $this->assertTrue($ticket->es_invitacion);
        $this->assertTrue($linea->fresh()->es_invitacion);
    }

    // ------------------------------------------------------------------
    // Cobros
    // ------------------------------------------------------------------

    public function test_el_ticket_se_cierra_al_cobrarlo_entero(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $this->gestor->cobrar($ticket, 'TARJETA', 22.00);

        $this->assertSame('COBRADO', $ticket->fresh()->estado);
    }

    public function test_se_puede_cobrar_con_varios_medios(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $this->gestor->cobrar($ticket, 'EFECTIVO', 10.00);
        $this->assertSame('ABIERTO', $ticket->fresh()->estado);
        $this->assertEqualsWithDelta(12.00, $ticket->fresh()->pendiente(), 0.01);

        $this->gestor->cobrar($ticket, 'TARJETA', 12.00);
        $this->assertSame('COBRADO', $ticket->fresh()->estado);
    }

    public function test_el_cambio_se_calcula_en_efectivo(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $cobro = $this->gestor->cobrar($ticket, 'EFECTIVO', 22.00, entregado: 50.00);

        $this->assertEqualsWithDelta(28.00, (float) $cobro->cambio, 0.01);
    }

    public function test_no_se_puede_cobrar_mas_de_lo_pendiente(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $this->expectException(\RuntimeException::class);

        $this->gestor->cobrar($ticket, 'TARJETA', 30.00);
    }

    public function test_la_venta_descuenta_stock(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->champu, 3);
        $this->gestor->cobrar($ticket, 'EFECTIVO', 54.00);

        $this->assertEqualsWithDelta(7.0, (float) $this->champu->fresh()->stock, 0.001);
    }

    public function test_anular_devuelve_el_stock(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->champu, 3);
        $this->gestor->cobrar($ticket, 'EFECTIVO', 54.00);

        $this->gestor->anular($ticket->fresh(), 'Error de cobro', $this->marta);

        $this->assertEqualsWithDelta(10.0, (float) $this->champu->fresh()->stock, 0.001);
        $this->assertSame('ANULADO', $ticket->fresh()->estado);
    }

    // ------------------------------------------------------------------
    // FORMACIÓN — lo más importante de esta fase
    // ------------------------------------------------------------------

    public function test_el_aprendiz_emite_en_serie_propia(): void
    {
        $ticket = $this->gestor->abrir($this->aprendiz);

        $this->assertSame(Ticket::SERIE_FORMACION, $ticket->serie);
        $this->assertTrue($ticket->es_formacion);
    }

    public function test_la_formacion_no_consume_numeracion_fiscal(): void
    {
        // Dos tickets reales
        $this->gestor->abrir($this->marta);
        $this->gestor->abrir($this->marta);

        // Tres de prácticas por medio
        $this->gestor->abrir($this->aprendiz);
        $this->gestor->abrir($this->aprendiz);
        $this->gestor->abrir($this->aprendiz);

        // El siguiente real debe ser el 3, no el 6
        $real = $this->gestor->abrir($this->marta);

        $this->assertSame(3, $real->numero);
        $this->assertSame(Ticket::SERIE_NORMAL, $real->serie);
    }

    public function test_el_aprendiz_solo_puede_cobrar_en_efectivo(): void
    {
        $ticket = $this->gestor->abrir($this->aprendiz);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('formación solo puede cobrar en efectivo');

        $this->gestor->cobrar($ticket, 'TARJETA', 22.00);
    }

    public function test_el_aprendiz_si_puede_cobrar_en_efectivo(): void
    {
        $ticket = $this->gestor->abrir($this->aprendiz);
        $this->gestor->anadirLinea($ticket, $this->corte);

        $this->gestor->cobrar($ticket, 'EFECTIVO', 22.00);

        $this->assertSame('COBRADO', Ticket::soloFormacion()->find($ticket->id)->estado);
    }

    public function test_los_tickets_de_formacion_son_invisibles_por_defecto(): void
    {
        $this->gestor->abrir($this->marta);
        $this->gestor->abrir($this->aprendiz);
        $this->gestor->abrir($this->aprendiz);

        $this->assertSame(1, Ticket::count(), 'Por defecto solo se ven los reales');
        $this->assertSame(3, Ticket::conFormacion()->count());
        $this->assertSame(2, Ticket::soloFormacion()->count());
    }

    public function test_la_formacion_queda_fuera_del_cierre_de_jornada(): void
    {
        // Venta real de 22 €
        $real = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($real, $this->corte);
        $this->gestor->cobrar($real, 'EFECTIVO', 22.00);

        // Prácticas por 44 €
        foreach ([1, 2] as $i) {
            $practica = $this->gestor->abrir($this->aprendiz);
            $this->gestor->anadirLinea($practica, $this->corte);
            $this->gestor->cobrar($practica, 'EFECTIVO', 22.00);
        }

        $cierre = (new GestorCierre())->cerrar($this->marta, 22.00);

        $this->assertSame(1, $cierre->num_tickets, 'Solo el ticket real entra en el cierre');
        $this->assertEqualsWithDelta(22.00, (float) $cierre->total_ventas, 0.01);
        $this->assertEqualsWithDelta(22.00, (float) $cierre->efectivo_teorico, 0.01,
            'Los 44 € de prácticas no deben aparecer en el arqueo');
        $this->assertEqualsWithDelta(0.00, (float) $cierre->descuadre, 0.01);
    }

    public function test_los_documentos_de_formacion_no_se_marcan_como_cerrados(): void
    {
        $practica = $this->gestor->abrir($this->aprendiz);
        $this->gestor->anadirLinea($practica, $this->corte);
        $this->gestor->cobrar($practica, 'EFECTIVO', 22.00);

        $real = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($real, $this->corte);
        $this->gestor->cobrar($real, 'EFECTIVO', 22.00);

        (new GestorCierre())->cerrar($this->marta, 22.00);

        $this->assertNull(Ticket::soloFormacion()->find($practica->id)->cierre_id,
            'Las prácticas quedan siempre pendientes de cierre, porque nunca entran');
    }

    public function test_los_documentos_de_formacion_se_pueden_borrar_sin_tocar_los_reales(): void
    {
        $real = $this->gestor->abrir($this->marta);
        $this->gestor->abrir($this->aprendiz);
        $this->gestor->abrir($this->aprendiz);

        Ticket::soloFormacion()->delete();

        $this->assertSame(0, Ticket::soloFormacion()->count());
        $this->assertSame(1, Ticket::count());
        $this->assertNotNull(Ticket::find($real->id));
    }

    // ------------------------------------------------------------------
    // Numeración
    // ------------------------------------------------------------------

    public function test_la_numeracion_es_correlativa_por_serie(): void
    {
        $primero = $this->gestor->abrir($this->marta);
        $segundo = $this->gestor->abrir($this->marta);

        $this->assertSame(1, $primero->numero);
        $this->assertSame(2, $segundo->numero);
        $this->assertSame('A-000001', $primero->referencia());
    }

    // ------------------------------------------------------------------
    // Cierre
    // ------------------------------------------------------------------

    public function test_el_arqueo_detecta_descuadres(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);
        $this->gestor->cobrar($ticket, 'EFECTIVO', 22.00);

        $cierre = (new GestorCierre())->cerrar($this->marta, 20.00);

        $this->assertEqualsWithDelta(-2.00, (float) $cierre->descuadre, 0.01);
        $this->assertTrue($cierre->hayDescuadre());
    }

    public function test_los_movimientos_de_caja_afectan_al_efectivo_teorico(): void
    {
        $gestorCierre = new GestorCierre();

        $gestorCierre->movimiento('APERTURA', 100.00, 'Fondo de caja', $this->marta);
        $gestorCierre->movimiento('SALIDA', 30.00, 'Pago mensajero', $this->marta);

        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);
        $this->gestor->cobrar($ticket, 'EFECTIVO', 22.00);

        // 100 fondo + 22 ventas - 30 salida = 92
        $this->assertEqualsWithDelta(92.00, $gestorCierre->resumen()['efectivo_teorico'], 0.01);
    }

    public function test_el_cobro_con_tarjeta_no_entra_en_el_arqueo_de_efectivo(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);
        $this->gestor->cobrar($ticket, 'TARJETA', 22.00);

        $resumen = (new GestorCierre())->resumen();

        $this->assertEqualsWithDelta(0.00, $resumen['efectivo_teorico'], 0.01);
        $this->assertEqualsWithDelta(22.00, $resumen['total_ventas'], 0.01);
    }

    public function test_un_ticket_cerrado_no_se_puede_anular(): void
    {
        $ticket = $this->gestor->abrir($this->marta);
        $this->gestor->anadirLinea($ticket, $this->corte);
        $this->gestor->cobrar($ticket, 'EFECTIVO', 22.00);

        (new GestorCierre())->cerrar($this->marta, 22.00);

        $this->expectException(\RuntimeException::class);

        $this->gestor->anular($ticket->fresh(), 'Tarde', $this->marta);
    }
}
