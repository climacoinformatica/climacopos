<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Bono;
use App\Models\BonoPlantilla;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\Vale;
use App\Services\GestorBonos;
use App\Services\GestorTickets;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class BonosTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorBonos $bonos;
    protected GestorTickets $tpv;
    protected Usuario $marta;
    protected Cliente $lucia;
    protected Articulo $manicura;
    protected Articulo $corte;
    protected BonoPlantilla $bono5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-bono-' . uniqid(),
            'nombre_comercial' => 'Salón Bonos',
            'email'            => 'bonos@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->bonos = new GestorBonos();
        $this->tpv = new GestorTickets();

        $this->marta = Usuario::create([
            'nombre'    => 'Marta',
            'perfil_id' => Perfil::where('clave', 'encargado')->value('id'),
            'pin'       => '1234',
        ]);

        $this->lucia = Cliente::create([
            'nombre'   => 'Lucía',
            'telefono' => '600111222',
        ]);

        $unas = Familia::create(['nombre' => 'Uñas', 'tipo' => 'SERVICIO']);
        $pelo = Familia::create(['nombre' => 'Peluquería', 'tipo' => 'SERVICIO']);

        $this->manicura = Articulo::create([
            'familia_id' => $unas->id, 'tipo' => 'SERVICIO', 'nombre' => 'Manicura',
            'precio' => 15.00, 'impuesto_pct' => 7.00,
        ]);

        $this->corte = Articulo::create([
            'familia_id' => $pelo->id, 'tipo' => 'SERVICIO', 'nombre' => 'Corte',
            'precio' => 22.00, 'impuesto_pct' => 7.00,
        ]);

        // 5 manicuras por 60 € en vez de 75
        $this->bono5 = BonoPlantilla::create([
            'nombre'          => 'Bono 5 manicuras',
            'modalidad'       => 'SESIONES',
            'precio'          => 60.00,
            'num_sesiones'    => 5,
            'articulo_id'     => $this->manicura->id,
            'caducidad_meses' => 12,
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
    // Venta
    // ------------------------------------------------------------------

    public function test_el_bono_se_emite_con_sus_sesiones(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);

        $this->assertSame(5, (int) $bono->sesiones_totales);
        $this->assertSame(0, (int) $bono->sesiones_usadas);
        $this->assertSame('ACTIVO', $bono->estado);
        $this->assertStringStartsWith('B-', $bono->codigo);
    }

    public function test_la_caducidad_se_fija_al_vender(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);

        $this->assertNotNull($bono->caduca_el);
        $this->assertEqualsWithDelta(365, now()->diffInDays($bono->caduca_el), 2);

        // Cambiar la plantilla no altera bonos ya vendidos
        $this->bono5->update(['caducidad_meses' => 1]);

        $this->assertEqualsWithDelta(365, now()->diffInDays($bono->fresh()->caduca_el), 2,
            'Cambiar condiciones a posteriori no se puede defender ante una clienta');
    }

    public function test_el_ahorro_se_calcula_frente_al_precio_suelto(): void
    {
        // 5 × 15 = 75, se venden a 60
        $this->assertEqualsWithDelta(15.00, $this->bono5->ahorro(), 0.01);
        $this->assertEqualsWithDelta(12.00, $this->bono5->precioPorSesion(), 0.01);
    }

    public function test_comprar_un_bono_en_el_tpv_lo_emite_al_cobrar(): void
    {
        $familia = Familia::first();

        $articuloBono = Articulo::create([
            'familia_id'        => $familia->id,
            'tipo'              => 'PRODUCTO',
            'nombre'            => 'Bono 5 manicuras',
            'precio'            => 60.00,
            'impuesto_pct'      => 7.00,
            'bono_plantilla_id' => $this->bono5->id,
        ]);

        $ticket = $this->tpv->abrir($this->marta);
        $ticket->update(['cliente_id' => $this->lucia->id]);
        $this->tpv->anadirLinea($ticket->fresh(), $articuloBono);

        $this->assertSame(0, Bono::count(), 'Todavía no se ha pagado');

        $this->tpv->cobrar($ticket->fresh(), 'EFECTIVO', 60.00);

        $this->assertSame(1, Bono::count());
        $this->assertSame($this->lucia->id, Bono::first()->cliente_id);
    }

    // ------------------------------------------------------------------
    // Consumo
    // ------------------------------------------------------------------

    public function test_usar_el_bono_descuenta_una_sesion_y_deja_la_linea_a_cero(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->manicura);

        $this->bonos->consumir($bono, $linea->fresh());

        $this->assertSame(4, $bono->fresh()->sesionesRestantes());
        $this->assertEqualsWithDelta(0.00, (float) $linea->fresh()->importe, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $ticket->fresh()->total, 0.01);
        $this->assertSame($bono->id, $linea->fresh()->bono_id);
    }

    public function test_el_bono_no_cubre_servicios_de_otra_familia(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->corte);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no cubre');

        $this->bonos->consumir($bono, $linea->fresh());
    }

    public function test_al_gastar_la_ultima_sesion_el_bono_queda_agotado(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);

        foreach (range(1, 5) as $i) {
            $ticket = $this->tpv->abrir($this->marta);
            $linea = $this->tpv->anadirLinea($ticket, $this->manicura);
            $this->bonos->consumir($bono->fresh(), $linea->fresh());
        }

        $this->assertSame('AGOTADO', $bono->fresh()->estado);
        $this->assertSame(0, $bono->fresh()->sesionesRestantes());
    }

    public function test_un_bono_agotado_no_se_puede_usar(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);
        $bono->update(['sesiones_usadas' => 5, 'estado' => 'AGOTADO']);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->manicura);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('agotado');

        $this->bonos->consumir($bono->fresh(), $linea->fresh());
    }

    public function test_un_bono_caducado_no_se_puede_usar_aunque_le_queden_sesiones(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);
        $bono->update(['caduca_el' => now()->subDay()->toDateString()]);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->manicura);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('caducó');

        $this->bonos->consumir($bono->fresh(), $linea->fresh());
    }

    public function test_solo_se_ofrecen_los_bonos_que_cubren_el_servicio(): void
    {
        $this->bonos->vender($this->bono5, $this->lucia);

        $this->assertCount(1, $this->bonos->bonosPara($this->lucia, $this->manicura));
        $this->assertCount(0, $this->bonos->bonosPara($this->lucia, $this->corte));
    }

    // ------------------------------------------------------------------
    // Bono de saldo
    // ------------------------------------------------------------------

    public function test_el_bono_de_saldo_descuenta_el_importe_real(): void
    {
        $plantilla = BonoPlantilla::create([
            'nombre'         => 'Recarga 100',
            'modalidad'      => 'SALDO',
            'precio'         => 100.00,
            'saldo_otorgado' => 120.00,
        ]);

        $bono = $this->bonos->vender($plantilla, $this->lucia);

        $this->assertEqualsWithDelta(120.00, (float) $bono->saldo_actual, 0.01);
        $this->assertEqualsWithDelta(20.00, $plantilla->ahorro(), 0.01);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->corte);   // 22 €

        $this->bonos->consumir($bono, $linea->fresh());

        $this->assertEqualsWithDelta(98.00, (float) $bono->fresh()->saldo_actual, 0.01);
    }

    public function test_el_bono_de_saldo_no_permite_gastar_de_mas(): void
    {
        $plantilla = BonoPlantilla::create([
            'nombre' => 'Recarga pequeña', 'modalidad' => 'SALDO',
            'precio' => 10.00, 'saldo_otorgado' => 10.00,
        ]);

        $bono = $this->bonos->vender($plantilla, $this->lucia);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->corte);   // 22 €

        $this->expectException(\RuntimeException::class);

        $this->bonos->consumir($bono, $linea->fresh());
    }

    // ------------------------------------------------------------------
    // Monedero
    // ------------------------------------------------------------------

    public function test_la_recarga_aumenta_el_saldo(): void
    {
        $this->bonos->recargarMonedero($this->lucia, 50.00);

        $this->assertEqualsWithDelta(50.00, (float) $this->lucia->fresh()->saldo_monedero, 0.01);
    }

    public function test_cobrar_con_monedero_descuenta_de_verdad(): void
    {
        $this->bonos->recargarMonedero($this->lucia, 50.00);

        $ticket = $this->tpv->abrir($this->marta);
        $ticket->update(['cliente_id' => $this->lucia->id]);
        $this->tpv->anadirLinea($ticket->fresh(), $this->corte);

        $this->tpv->cobrar($ticket->fresh(), 'MONEDERO', 22.00);

        $this->assertEqualsWithDelta(28.00, (float) $this->lucia->fresh()->saldo_monedero, 0.01);
        $this->assertSame('COBRADO', $ticket->fresh()->estado);
    }

    public function test_no_se_puede_cobrar_del_monedero_sin_saldo(): void
    {
        $this->bonos->recargarMonedero($this->lucia, 10.00);

        $ticket = $this->tpv->abrir($this->marta);
        $ticket->update(['cliente_id' => $this->lucia->id]);
        $this->tpv->anadirLinea($ticket->fresh(), $this->corte);

        $this->expectException(\RuntimeException::class);

        $this->tpv->cobrar($ticket->fresh(), 'MONEDERO', 22.00);
    }

    public function test_el_monedero_exige_cliente_en_el_ticket(): void
    {
        $ticket = $this->tpv->abrir($this->marta);
        $this->tpv->anadirLinea($ticket, $this->corte);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('asignar el cliente');

        $this->tpv->cobrar($ticket->fresh(), 'MONEDERO', 22.00);
    }

    public function test_un_cobro_fallido_no_deja_el_monedero_tocado(): void
    {
        $this->bonos->recargarMonedero($this->lucia, 10.00);

        $ticket = $this->tpv->abrir($this->marta);
        $ticket->update(['cliente_id' => $this->lucia->id]);
        $this->tpv->anadirLinea($ticket->fresh(), $this->corte);

        try {
            $this->tpv->cobrar($ticket->fresh(), 'MONEDERO', 22.00);
        } catch (\RuntimeException) {
        }

        $this->assertEqualsWithDelta(10.00, (float) $this->lucia->fresh()->saldo_monedero, 0.01,
            'Si el cobro no sale, el saldo se queda como estaba');
        $this->assertSame(0, $ticket->fresh()->cobros()->count());
    }

    // ------------------------------------------------------------------
    // Vales
    // ------------------------------------------------------------------

    public function test_el_codigo_del_vale_evita_caracteres_ambiguos(): void
    {
        $vale = $this->bonos->emitirVale(25.00);

        $this->assertStringStartsWith('V-', $vale->codigo);
        $this->assertDoesNotMatchRegularExpression('/[OIL01]/', substr($vale->codigo, 2),
            'Se dicta por teléfono y se teclea a mano');
    }

    public function test_canjear_un_vale_menor_que_el_ticket_deja_el_vale_agotado(): void
    {
        $vale = $this->bonos->emitirVale(10.00);

        $aplicado = $this->bonos->canjearVale($vale, 10.00);

        $this->assertEqualsWithDelta(10.00, $aplicado, 0.01);
        $this->assertSame('CANJEADO', $vale->fresh()->estado);
    }

    public function test_un_vale_mayor_que_el_ticket_conserva_el_resto(): void
    {
        $vale = $this->bonos->emitirVale(50.00);

        $aplicado = $this->bonos->canjearVale($vale, 22.00);

        $this->assertEqualsWithDelta(22.00, $aplicado, 0.01);
        $this->assertEqualsWithDelta(28.00, (float) $vale->fresh()->importe_restante, 0.01,
            'Devolver la diferencia convertiría un vale en dinero');
        $this->assertSame('ACTIVO', $vale->fresh()->estado);
    }

    public function test_cobrar_con_vale_desde_el_tpv(): void
    {
        $vale = $this->bonos->emitirVale(30.00);

        $ticket = $this->tpv->abrir($this->marta);
        $this->tpv->anadirLinea($ticket, $this->corte);

        $this->tpv->cobrar($ticket->fresh(), 'VALE', 22.00, referencia: $vale->codigo);

        $this->assertSame('COBRADO', $ticket->fresh()->estado);
        $this->assertEqualsWithDelta(8.00, (float) $vale->fresh()->importe_restante, 0.01);
    }

    public function test_un_vale_inexistente_da_error_claro(): void
    {
        $ticket = $this->tpv->abrir($this->marta);
        $this->tpv->anadirLinea($ticket, $this->corte);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No existe ningun vale');

        $this->tpv->cobrar($ticket->fresh(), 'VALE', 22.00, referencia: 'V-INVENTADO');
    }

    public function test_un_vale_caducado_no_se_canjea(): void
    {
        $vale = $this->bonos->emitirVale(30.00);
        $vale->update(['caduca_el' => now()->subDay()->toDateString()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('caducó');

        $this->bonos->canjearVale($vale->fresh(), 10.00);
    }

    // ------------------------------------------------------------------
    // Mantenimiento
    // ------------------------------------------------------------------

    public function test_la_tarea_nocturna_marca_los_vencidos(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);
        $bono->update(['caduca_el' => now()->subDays(3)->toDateString()]);

        $vale = $this->bonos->emitirVale(20.00);
        $vale->update(['caduca_el' => now()->subDays(3)->toDateString()]);

        $resultado = $this->bonos->caducarVencidos();

        $this->assertSame(1, $resultado['bonos']);
        $this->assertSame(1, $resultado['vales']);
        $this->assertSame('CADUCADO', $bono->fresh()->estado);
        $this->assertSame('CADUCADO', $vale->fresh()->estado);
    }

    public function test_cada_movimiento_del_bono_queda_registrado(): void
    {
        $bono = $this->bonos->vender($this->bono5, $this->lucia);

        $ticket = $this->tpv->abrir($this->marta);
        $linea = $this->tpv->anadirLinea($ticket, $this->manicura);
        $this->bonos->consumir($bono, $linea->fresh());

        $movimientos = $bono->fresh()->movimientos;

        $this->assertCount(2, $movimientos);
        $this->assertSame('CONSUMO', $movimientos->first()->tipo);
        $this->assertSame('COMPRA', $movimientos->last()->tipo);
    }
}
