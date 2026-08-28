<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Aviso;
use App\Models\Cliente;
use App\Models\ConfigEmpresa;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\UsuarioHorario;
use App\Services\GestorReservas;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class PortalReservasTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected Usuario $marta;
    protected Articulo $corte;
    protected Carbon $lunes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-portal-' . uniqid(),
            'nombre_comercial' => 'Salon Portal',
            'email'            => 'p@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->lunes = Carbon::now()->addWeek()->startOfWeek(Carbon::MONDAY);

        $this->marta = Usuario::create([
            'nombre'         => 'Marta',
            'perfil_id'      => Perfil::where('clave', 'profesional')->value('id'),
            'es_profesional' => true,
            'pin'            => '1234',
        ]);

        UsuarioHorario::create([
            'usuario_id' => $this->marta->id,
            'dia_semana' => 1,
            'hora_ini'   => '09:00',
            'hora_fin'   => '18:00',
        ]);

        $familia = Familia::create(['nombre' => 'Corte', 'tipo' => 'SERVICIO']);

        $this->corte = Articulo::create([
            'familia_id'             => $familia->id,
            'tipo'                   => 'SERVICIO',
            'nombre'                 => 'Corte de señora',
            'precio'                 => 22.00,
            'impuesto_pct'           => 7.00,
            'duracion_min'           => 45,
            'permite_reserva_online' => true,
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

    protected function reservarOnline(string $hora = '10:00', array $extra = []): Reserva
    {
        return (new GestorReservas())->crear(
            $this->lunes,
            $hora,
            [['articulo_id' => $this->corte->id, 'usuario_id' => $this->marta->id]],
            array_merge([
                'nombre'      => 'Lucía',
                'telefono'    => '600' . random_int(100000, 999999),
                'email'       => 'lucia@ejemplo.com',
                'acepta_rgpd' => true,
            ], $extra),
            origen: 'ONLINE',
        );
    }

    // ------------------------------------------------------------------
    // Estado inicial de una reserva online
    // ------------------------------------------------------------------

    public function test_una_reserva_online_nace_pendiente_por_defecto(): void
    {
        $reserva = $this->reservarOnline();

        $this->assertSame('PENDIENTE', $reserva->estado);
        $this->assertSame('ONLINE', $reserva->origen);
    }

    public function test_con_confirmacion_automatica_nace_confirmada(): void
    {
        ConfigEmpresa::updateOrCreate(['clave' => 'confirmacion_automatica'], ['valor' => 'true']);

        // El helper cachea en memoria durante la petición: en el test hay
        // que forzar la relectura creando la reserva en un estado limpio.
        $this->refreshApplication();
        tenancy()->initialize($this->empresa);

        $reserva = $this->reservarOnline('11:00');

        $this->assertSame('CONFIRMADA', $reserva->estado);
    }

    public function test_una_reserva_local_nunca_queda_pendiente(): void
    {
        $reserva = (new GestorReservas())->crear(
            $this->lunes,
            '12:00',
            [['articulo_id' => $this->corte->id, 'usuario_id' => $this->marta->id]],
            ['nombre' => 'Ana', 'telefono' => '600999888'],
            origen: 'LOCAL',
        );

        $this->assertSame('CONFIRMADA', $reserva->estado);
    }

    // ------------------------------------------------------------------
    // Avisos
    // ------------------------------------------------------------------

    public function test_una_reserva_online_genera_aviso_que_exige_accion(): void
    {
        $reserva = $this->reservarOnline();

        $aviso = Aviso::where('referencia_id', $reserva->id)->first();

        $this->assertNotNull($aviso);
        $this->assertSame('RESERVA_NUEVA', $aviso->tipo);
        $this->assertTrue($aviso->requiere_accion);
        $this->assertFalse($aviso->resuelto);
    }

    public function test_una_reserva_local_no_genera_aviso(): void
    {
        (new GestorReservas())->crear(
            $this->lunes,
            '12:00',
            [['articulo_id' => $this->corte->id, 'usuario_id' => $this->marta->id]],
            ['nombre' => 'Ana', 'telefono' => '600999888'],
            origen: 'LOCAL',
        );

        $this->assertSame(0, Aviso::count());
    }

    public function test_leer_un_aviso_de_accion_no_lo_apaga(): void
    {
        $reserva = $this->reservarOnline();
        $aviso = Aviso::where('referencia_id', $reserva->id)->first();

        $aviso->marcarLeido();

        $this->assertTrue($aviso->fresh()->leido);
        $this->assertFalse($aviso->fresh()->resuelto,
            'Un aviso que exige acción no se apaga solo por leerlo');
        $this->assertSame(1, Aviso::queDestellan()->count(),
            'Debe seguir destellando hasta que se resuelva la reserva');
    }

    public function test_confirmar_la_reserva_apaga_el_aviso(): void
    {
        $reserva = $this->reservarOnline();

        $reserva->confirmar($this->marta);
        Aviso::resolverDeReserva($reserva->id);

        $this->assertSame(0, Aviso::queDestellan()->count());
    }

    public function test_rechazar_la_reserva_apaga_el_aviso(): void
    {
        $reserva = $this->reservarOnline();

        $reserva->rechazar('Sin disponibilidad', $this->marta);
        Aviso::resolverDeReserva($reserva->id);

        $this->assertSame(0, Aviso::queDestellan()->count());
        $this->assertSame('RECHAZADA', $reserva->fresh()->estado);
    }

    public function test_la_huella_cambia_al_llegar_una_reserva(): void
    {
        $antes = Aviso::huella();

        $this->reservarOnline();

        $this->assertNotSame($antes, Aviso::huella(),
            'El panel detecta novedades comparando esta huella');
    }

    // ------------------------------------------------------------------
    // Cliente y RGPD
    // ------------------------------------------------------------------

    public function test_la_reserva_online_crea_la_ficha_con_consentimiento(): void
    {
        $reserva = $this->reservarOnline(extra: ['telefono' => '600112233']);

        $cliente = Cliente::find($reserva->cliente_id);

        $this->assertNotNull($cliente);
        $this->assertSame('ONLINE', $cliente->origen);
        $this->assertTrue($cliente->acepta_rgpd);
        $this->assertNotNull($cliente->fecha_consentimiento,
            'Hay que poder demostrar cuándo se dio el consentimiento');
    }

    public function test_el_cliente_que_vuelve_reutiliza_su_ficha(): void
    {
        $primera = $this->reservarOnline('10:00', ['telefono' => '600 11 22 33']);
        $segunda = $this->reservarOnline('14:00', ['telefono' => '600112233']);

        $this->assertSame($primera->cliente_id, $segunda->cliente_id);
        $this->assertSame(1, Cliente::count());
    }

    // ------------------------------------------------------------------
    // Retención del hueco
    // ------------------------------------------------------------------

    public function test_una_reserva_pendiente_bloquea_el_hueco(): void
    {
        $this->reservarOnline('10:00');

        $motor = new \App\Services\MotorHuecos();
        $huecos = $motor->huecosDe($this->lunes, $this->corte, $this->marta);

        $this->assertNotContains('10:00', $huecos,
            'Mientras el salón decide, nadie más puede coger ese hueco');
    }

    // ------------------------------------------------------------------
    // Códigos
    // ------------------------------------------------------------------

    public function test_el_codigo_sirve_para_consultar_la_cita(): void
    {
        $reserva = $this->reservarOnline();

        $encontrada = Reserva::where('codigo', $reserva->codigo)->first();

        $this->assertNotNull($encontrada);
        $this->assertSame($reserva->id, $encontrada->id);
    }

    // ------------------------------------------------------------------
    // Mantenimiento
    // ------------------------------------------------------------------

    public function test_las_pendientes_antiguas_se_pueden_caducar(): void
    {
        $reserva = $this->reservarOnline();
        $reserva->update(['created_at' => now()->subHours(72)]);

        $caducadas = Reserva::pendientes()
            ->where('created_at', '<=', now()->subHours(48))
            ->get();

        $this->assertCount(1, $caducadas);

        $caducadas->first()->rechazar('No se pudo atender a tiempo.');

        $this->assertSame('RECHAZADA', $reserva->fresh()->estado);
    }
}
