<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\PagoOnline;
use App\Models\Perfil;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\UsuarioHorario;
use App\Services\GestorReservas;
use App\Services\GestorTickets;
use App\Services\Pagos\PasarelaStripe;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

/**
 * No se llama a Stripe en ningún test: se comprueba la lógica propia
 * (importes, estados, anticipos, devoluciones) y la firma del webhook,
 * que es lo que de verdad puede romperse por nuestra parte.
 */
class PagosTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected Usuario $marta;
    protected Articulo $mechas;
    protected Carbon $lunes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'                  => 'test-pago-' . uniqid(),
            'nombre_comercial'      => 'Salón Pagos',
            'email'                 => 'p@test.local',
            'stripe_connect_id'     => 'acct_prueba',
            'stripe_connect_estado' => 'ACTIVA',
            'stripe_cobros_activos' => true,
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->lunes = Carbon::now()->addWeek()->startOfWeek(Carbon::MONDAY);

        $this->marta = Usuario::create([
            'nombre'         => 'Marta',
            'perfil_id'      => Perfil::where('clave', 'encargado')->value('id'),
            'es_profesional' => true,
            'pin'            => '1234',
        ]);

        UsuarioHorario::create([
            'usuario_id' => $this->marta->id,
            'dia_semana' => 1, 'hora_ini' => '09:00', 'hora_fin' => '18:00',
        ]);

        $familia = Familia::create(['nombre' => 'Color', 'tipo' => 'SERVICIO']);

        $this->mechas = Articulo::create([
            'familia_id'     => $familia->id,
            'tipo'           => 'SERVICIO',
            'nombre'         => 'Mechas',
            'precio'         => 85.00,
            'impuesto_pct'   => 7.00,
            'duracion_min'   => 120,
            'politica_pago'  => 'FIANZA',
            'fianza_importe' => 20.00,
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

    protected function reservar(string $hora = '10:00'): Reserva
    {
        return (new GestorReservas())->crear(
            $this->lunes,
            $hora,
            [['articulo_id' => $this->mechas->id, 'usuario_id' => $this->marta->id]],
            ['nombre' => 'Lucía', 'telefono' => '600' . random_int(100000, 999999), 'acepta_rgpd' => true],
            origen: 'ONLINE',
        );
    }

    protected function pagoDe(Reserva $reserva, float $importe = 20.00, string $estado = 'PAGADO'): PagoOnline
    {
        return PagoOnline::create([
            'reserva_id' => $reserva->id,
            'cliente_id' => $reserva->cliente_id,
            'pasarela'   => 'STRIPE',
            'tipo'       => 'FIANZA',
            'importe'    => $importe,
            'estado'     => $estado,
            'cargo_id'   => 'pi_prueba_' . uniqid(),
            'pagado_en'  => $estado === 'PAGADO' ? now() : null,
        ]);
    }

    // ------------------------------------------------------------------
    // Importes de fianza
    // ------------------------------------------------------------------

    public function test_la_fianza_por_importe_fijo(): void
    {
        $this->assertEqualsWithDelta(20.00, $this->mechas->importeFianza(), 0.01);
    }

    public function test_la_fianza_por_porcentaje(): void
    {
        $this->mechas->update(['fianza_importe' => null, 'fianza_pct' => 30]);

        $this->assertEqualsWithDelta(25.50, $this->mechas->fresh()->importeFianza(), 0.01);
    }

    public function test_el_pago_total_reserva_el_precio_completo(): void
    {
        $this->mechas->update(['politica_pago' => 'TOTAL']);

        $this->assertEqualsWithDelta(85.00, $this->mechas->fresh()->importeFianza(), 0.01);
    }

    // ------------------------------------------------------------------
    // Anticipos en el TPV
    // ------------------------------------------------------------------

    public function test_la_reserva_conoce_su_anticipo(): void
    {
        $reserva = $this->reservar();
        $this->pagoDe($reserva, 20.00);

        $this->assertTrue($reserva->fresh()->tieneAnticipo());
        $this->assertEqualsWithDelta(20.00, $reserva->fresh()->anticipo(), 0.01);
    }

    public function test_un_pago_devuelto_ya_no_cuenta_como_anticipo(): void
    {
        $reserva = $this->reservar();
        $pago = $this->pagoDe($reserva, 20.00);

        $pago->update(['estado' => 'DEVUELTO', 'devuelto_importe' => 20.00]);

        $this->assertEqualsWithDelta(0.00, $reserva->fresh()->anticipo(), 0.01);
        $this->assertFalse($reserva->fresh()->tieneAnticipo());
    }

    public function test_el_ticket_de_una_cita_pagada_solo_pide_la_diferencia(): void
    {
        $reserva = $this->reservar();
        $this->pagoDe($reserva, 20.00);
        $reserva->confirmar($this->marta);

        $ticket = (new GestorTickets())->abrir($this->marta, $reserva->fresh());

        // 85 de servicio - 20 ya pagados = 65 pendientes
        $this->assertEqualsWithDelta(85.00, (float) $ticket->fresh()->total, 0.01);
        $this->assertEqualsWithDelta(65.00, $ticket->fresh()->pendiente(), 0.01);

        $anticipo = $ticket->cobros()->where('medio', 'ANTICIPO')->first();
        $this->assertNotNull($anticipo, 'Sin esto se le cobraría dos veces al cliente');
        $this->assertEqualsWithDelta(20.00, (float) $anticipo->importe, 0.01);
    }

    public function test_el_anticipo_no_se_aplica_dos_veces(): void
    {
        $reserva = $this->reservar();
        $this->pagoDe($reserva, 20.00);

        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->marta, $reserva->fresh());

        $gestor->aplicarAnticipo($ticket->fresh());

        $this->assertSame(1, $ticket->cobros()->where('medio', 'ANTICIPO')->count());
    }

    public function test_una_cita_sin_pago_se_cobra_entera(): void
    {
        $this->mechas->update(['politica_pago' => 'NINGUNO']);

        $reserva = $this->reservar();
        $ticket = (new GestorTickets())->abrir($this->marta, $reserva);

        $this->assertEqualsWithDelta(85.00, $ticket->fresh()->pendiente(), 0.01);
        $this->assertSame(0, $ticket->cobros()->count());
    }

    // ------------------------------------------------------------------
    // Plazos de cancelación
    // ------------------------------------------------------------------

    public function test_una_cita_lejana_esta_en_plazo_de_cancelacion(): void
    {
        $reserva = $this->reservar();

        $this->assertTrue($reserva->enPlazoDeCancelacion());
    }

    public function test_una_cita_inminente_esta_fuera_de_plazo(): void
    {
        $reserva = $this->reservar();
        $reserva->update([
            'fecha'    => now()->toDateString(),
            'hora_ini' => now()->addHour()->format('H:i:s'),
        ]);

        $this->assertFalse($reserva->fresh()->enPlazoDeCancelacion(),
            'Con 24 h de política, una cita dentro de una hora no admite cancelación online');
    }

    // ------------------------------------------------------------------
    // Estados del pago
    // ------------------------------------------------------------------

    public function test_un_pago_pagado_es_devolvible(): void
    {
        $pago = $this->pagoDe($this->reservar());

        $this->assertTrue($pago->esDevolvible());
        $this->assertEqualsWithDelta(20.00, $pago->pendienteDevolver(), 0.01);
    }

    public function test_un_pago_ya_devuelto_no_admite_otra_devolucion(): void
    {
        $pago = $this->pagoDe($this->reservar());
        $pago->update(['devuelto_importe' => 20.00, 'estado' => 'DEVUELTO']);

        $this->assertFalse($pago->fresh()->esDevolvible());
    }

    public function test_una_devolucion_parcial_deja_saldo_pendiente(): void
    {
        $pago = $this->pagoDe($this->reservar());
        $pago->update(['devuelto_importe' => 8.00, 'estado' => 'DEVUELTO_PARCIAL']);

        $this->assertTrue($pago->fresh()->esDevolvible());
        $this->assertEqualsWithDelta(12.00, $pago->fresh()->pendienteDevolver(), 0.01);
    }

    public function test_la_comision_de_la_plataforma_se_descuenta_del_neto(): void
    {
        $pago = $this->pagoDe($this->reservar());
        $pago->update(['comision_plataforma' => 1.50]);

        $this->assertEqualsWithDelta(18.50, $pago->fresh()->netoParaElSalon(), 0.01);
    }

    public function test_un_pago_iniciado_caduca(): void
    {
        $pago = $this->pagoDe($this->reservar(), 20.00, 'INICIADO');
        $pago->update(['caduca_en' => now()->subMinute()]);

        $this->assertTrue($pago->fresh()->haCaducado());
    }

    // ------------------------------------------------------------------
    // Seguridad del webhook
    // ------------------------------------------------------------------

    public function test_el_webhook_rechaza_una_firma_invalida(): void
    {
        $pasarela = new PasarelaStripe(clave: 'sk_test', secretoWebhook: 'whsec_secreto');

        $this->assertFalse(
            $pasarela->verificarFirma('{"tipo":"prueba"}', 't=' . time() . ',v1=inventada'),
            'Sin verificar la firma, cualquiera podría fingir un pago'
        );
    }

    public function test_el_webhook_acepta_una_firma_correcta(): void
    {
        $secreto = 'whsec_secreto';
        $cuerpo  = '{"type":"checkout.session.completed"}';
        $marca   = time();
        $firma   = hash_hmac('sha256', $marca . '.' . $cuerpo, $secreto);

        $pasarela = new PasarelaStripe(clave: 'sk_test', secretoWebhook: $secreto);

        $this->assertTrue($pasarela->verificarFirma($cuerpo, "t={$marca},v1={$firma}"));
    }

    public function test_el_webhook_rechaza_eventos_antiguos(): void
    {
        $secreto = 'whsec_secreto';
        $cuerpo  = '{"type":"checkout.session.completed"}';
        $marca   = time() - 3600;   // hace una hora
        $firma   = hash_hmac('sha256', $marca . '.' . $cuerpo, $secreto);

        $pasarela = new PasarelaStripe(clave: 'sk_test', secretoWebhook: $secreto);

        $this->assertFalse($pasarela->verificarFirma($cuerpo, "t={$marca},v1={$firma}"),
            'Un evento viejo reenviado podría duplicar un cobro');
    }

    public function test_sin_secreto_configurado_no_se_acepta_ningun_webhook(): void
    {
        $pasarela = new PasarelaStripe(clave: 'sk_test', secretoWebhook: null);

        $this->assertFalse($pasarela->verificarFirma('{}', 't=1,v1=x'));
    }
}
