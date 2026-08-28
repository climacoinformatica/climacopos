<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plan;
use App\Services\GestorSuscripciones;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class SuscripcionesTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorSuscripciones $gestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = new GestorSuscripciones();

        $this->empresa = Empresa::create([
            'slug'             => 'test-susc-' . uniqid(),
            'nombre_comercial' => 'Salón Suscripción',
            'email'            => 's@test.local',
            'estado'           => 'ACTIVA',
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
    // Ciclo de morosidad
    // ------------------------------------------------------------------

    public function test_el_primer_impago_solo_avisa(): void
    {
        $estado = $this->gestor->registrarImpago($this->empresa);

        $this->assertSame('MOROSA', $estado);
        $this->assertSame(1, $this->empresa->fresh()->impagos);

        $this->assertTrue(GestorSuscripciones::puedeEscribir($this->empresa->fresh()),
            'Con un solo impago el salón sigue trabajando con normalidad');
    }

    public function test_el_segundo_impago_deja_la_cuenta_en_solo_lectura(): void
    {
        $this->gestor->registrarImpago($this->empresa);
        $estado = $this->gestor->registrarImpago($this->empresa->fresh());

        $this->assertSame('SUSPENDIDA', $estado);
        $this->assertSame(2, $this->empresa->fresh()->impagos);
    }

    public function test_la_suspension_no_entra_en_mitad_de_la_jornada(): void
    {
        $this->gestor->registrarImpago($this->empresa);
        $this->gestor->registrarImpago($this->empresa->fresh());

        $empresa = $this->empresa->fresh();

        $this->assertNotNull($empresa->suspension_efectiva_en);
        $this->assertTrue($empresa->suspension_efectiva_en->isFuture());
        $this->assertSame(4, $empresa->suspension_efectiva_en->hour,
            'La suspensión se programa para las 4 de la madrugada');

        $this->assertTrue(GestorSuscripciones::puedeEscribir($empresa),
            'Hasta que llegue la hora, el TPV sigue funcionando: '
            . 'bloquearlo con clientas esperando pierde al cliente');
    }

    public function test_llegada_la_hora_la_cuenta_queda_en_solo_lectura(): void
    {
        $this->empresa->forceFill([
            'estado'                 => 'SUSPENDIDA',
            'suspension_efectiva_en' => now()->subHour(),
        ])->save();

        $empresa = $this->empresa->fresh();

        $this->assertFalse(GestorSuscripciones::puedeEscribir($empresa));
        $this->assertTrue(GestorSuscripciones::enSoloLectura($empresa));
    }

    public function test_una_cuenta_suspendida_no_puede_ver_informes(): void
    {
        $this->empresa->forceFill([
            'estado'                 => 'SUSPENDIDA',
            'suspension_efectiva_en' => now()->subHour(),
        ])->save();

        $this->assertFalse(GestorSuscripciones::puedeVerInformes($this->empresa->fresh()),
            'Los informes son lo único que un salón suspendido podría aprovechar '
            . 'de verdad: exportar todo y marcharse sin pagar');
    }

    public function test_el_borrado_se_programa_a_noventa_dias(): void
    {
        $this->gestor->registrarImpago($this->empresa);
        $this->gestor->registrarImpago($this->empresa->fresh());

        $empresa = $this->empresa->fresh();

        $this->assertNotNull($empresa->borrar_a_partir_de);
        $this->assertEqualsWithDelta(
            90,
            now()->diffInDays($empresa->borrar_a_partir_de),
            1,
        );
    }

    // ------------------------------------------------------------------
    // Recuperación
    // ------------------------------------------------------------------

    public function test_pagar_borra_el_historial_de_impagos(): void
    {
        $this->gestor->registrarImpago($this->empresa);
        $this->gestor->registrarImpago($this->empresa->fresh());

        $this->gestor->activar($this->empresa->fresh());

        $empresa = $this->empresa->fresh();

        $this->assertSame('ACTIVA', $empresa->estado);
        $this->assertSame(0, $empresa->impagos);
        $this->assertNull($empresa->suspension_efectiva_en);
        $this->assertNull($empresa->borrar_a_partir_de);
        $this->assertTrue(GestorSuscripciones::puedeEscribir($empresa));
    }

    public function test_una_cuenta_activa_puede_todo(): void
    {
        $this->assertTrue(GestorSuscripciones::puedeEscribir($this->empresa));
        $this->assertTrue(GestorSuscripciones::puedeVerInformes($this->empresa));
        $this->assertFalse(GestorSuscripciones::enSoloLectura($this->empresa));
    }

    public function test_una_cuenta_en_prueba_puede_todo(): void
    {
        $this->empresa->forceFill([
            'estado'       => 'PRUEBA',
            'prueba_hasta' => now()->addDays(10),
        ])->save();

        $this->assertTrue(GestorSuscripciones::puedeEscribir($this->empresa->fresh()));
    }

    public function test_una_cuenta_morosa_puede_todo(): void
    {
        $this->empresa->forceFill(['estado' => 'MOROSA', 'impagos' => 1])->save();

        $this->assertTrue(GestorSuscripciones::puedeEscribir($this->empresa->fresh()),
            'El primer impago solo avisa; cortar a la primera espanta clientes');
    }

    public function test_una_cuenta_cancelada_queda_en_solo_lectura(): void
    {
        $this->empresa->forceFill([
            'estado'                 => 'CANCELADA',
            'suspension_efectiva_en' => now()->subDay(),
        ])->save();

        $this->assertFalse(GestorSuscripciones::puedeEscribir($this->empresa->fresh()));
    }

    // ------------------------------------------------------------------
    // Facturas
    // ------------------------------------------------------------------

    public function test_una_factura_de_stripe_se_registra_traducida(): void
    {
        $factura = $this->gestor->registrarFactura($this->empresa, [
            'id'           => 'in_prueba_' . uniqid(),
            'number'       => 'F-0001',
            'amount_due'   => 3900,
            'tax'          => 0,
            'currency'     => 'eur',
            'status'       => 'paid',
            'period_start' => now()->startOfMonth()->timestamp,
            'period_end'   => now()->endOfMonth()->timestamp,
            'attempt_count'=> 1,
        ]);

        $this->assertEqualsWithDelta(39.00, (float) $factura->importe, 0.01,
            'Stripe devuelve céntimos: 3900 son 39,00 €');
        $this->assertSame('PAGADA', $factura->estado);
        $this->assertSame('F-0001', $factura->numero);
    }

    public function test_una_factura_impagada_se_marca_como_tal(): void
    {
        $factura = $this->gestor->registrarFactura($this->empresa, [
            'id'       => 'in_prueba_' . uniqid(),
            'amount_due' => 1900,
            'currency' => 'eur',
            'status'   => 'open',
        ]);

        $this->assertSame('PENDIENTE', $factura->estado);
    }

    public function test_la_misma_factura_no_se_duplica(): void
    {
        $id = 'in_prueba_' . uniqid();

        $this->gestor->registrarFactura($this->empresa, [
            'id' => $id, 'amount_due' => 1900, 'currency' => 'eur', 'status' => 'open',
        ]);

        $this->gestor->registrarFactura($this->empresa, [
            'id' => $id, 'amount_due' => 1900, 'currency' => 'eur', 'status' => 'paid',
        ]);

        $facturas = \App\Models\FacturaPlataforma::where('empresa_id', $this->empresa->id)->get();

        $this->assertCount(1, $facturas, 'Stripe reenvía eventos: no puede duplicar facturas');
        $this->assertSame('PAGADA', $facturas->first()->estado);
    }
}
