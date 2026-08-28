<?php

namespace Tests\Feature;

use App\Models\ConfigPlataforma;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Plan;
use App\Models\Terminal;
use App\Models\Usuario;
use App\Services\Correo\CorreosPlataforma;
use App\Services\GestorSuscripciones;
use App\Support\LimitesPlan;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class LimitesYAvisosTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected Plan $planBasico;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigPlataforma::guardar('correo_host', 'smtp.ejemplo.com');
        ConfigPlataforma::guardar('correo_remitente', 'avisos@ejemplo.com');

        $this->planBasico = Plan::firstOrCreate(
            ['slug' => 'test-basico'],
            [
                'nombre'            => 'Básico de prueba',
                'precio_mes'        => 19.00,
                'max_profesionales' => 2,
                'max_terminales'    => 1,
                'reservas_online'   => true,
                'pagos_online'      => false,
                'verifactu'         => false,
                'activo'            => true,
                'orden'             => 99,
            ],
        );

        $this->empresa = Empresa::create([
            'slug'             => 'test-lim-' . uniqid(),
            'nombre_comercial' => 'Salón Límites',
            'email'            => 'lim@ejemplo.com',
            'plan_id'          => $this->planBasico->id,
            'estado'           => 'ACTIVA',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        ConfigPlataforma::query()->delete();

        if ($this->empresa) {
            try {
                (new DeleteDatabase($this->empresa))->handle();
            } catch (\Throwable) {
            }

            $this->empresa->domains()->delete();
            $this->empresa->forceDelete();
        }

        Plan::where('slug', 'test-basico')->delete();

        parent::tearDown();
    }

    /**
     * Mensajes capturados por el transporte de pruebas.
     *
     * No se usa Mail::fake() porque solo registra Mailables, y estos
     * correos se mandan con Mail::send() y una vista. El transporte
     * 'array' del .env.testing si los guarda enteros.
     *
     * @return array<int, string>  Asuntos de los correos enviados
     */
    protected function asuntosEnviados(): array
    {
        $transporte = app('mailer')->getSymfonyTransport();

        if (! $transporte instanceof ArrayTransport) {
            $this->markTestSkipped('El entorno de pruebas no usa MAIL_MAILER=array. '
                . 'Ejecuta herramientas\\preparar_pruebas.ps1');
        }

        return $transporte->messages()
            ->map(fn ($mensaje) => $mensaje->getOriginalMessage()->getSubject())
            ->all();
    }

    protected function limpiarCorreos(): void
    {
        $transporte = app('mailer')->getSymfonyTransport();

        if ($transporte instanceof ArrayTransport) {
            $transporte->flush();
        }
    }

    protected function crearProfesional(string $nombre): Usuario
    {
        return Usuario::create([
            'nombre'         => $nombre,
            'perfil_id'      => Perfil::where('clave', 'profesional')->value('id'),
            'es_profesional' => true,
            'pin'            => (string) random_int(1000, 9999),
        ]);
    }

    // ------------------------------------------------------------------
    // Límites
    // ------------------------------------------------------------------

    public function test_el_plan_limita_el_numero_de_profesionales(): void
    {
        $this->assertSame(2, LimitesPlan::profesionalesDisponibles());

        $this->crearProfesional('Marta');
        $this->assertSame(1, LimitesPlan::profesionalesDisponibles());

        $this->crearProfesional('Lucía');
        $this->assertSame(0, LimitesPlan::profesionalesDisponibles());
    }

    public function test_pasado_el_limite_no_se_puede_anadir_otro(): void
    {
        $this->crearProfesional('Marta');
        $this->crearProfesional('Lucía');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cambia de plan');

        LimitesPlan::comprobarProfesional();
    }

    public function test_el_mensaje_dice_que_hacer_no_solo_que_no_se_puede(): void
    {
        $this->crearProfesional('Marta');
        $this->crearProfesional('Lucía');

        try {
            LimitesPlan::comprobarProfesional();
            $this->fail('Debería haber lanzado excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Básico de prueba', $e->getMessage());
            $this->assertStringContainsString('2 profesional', $e->getMessage());
            $this->assertStringContainsString('Suscripción', $e->getMessage());
        }
    }

    public function test_los_usuarios_no_profesionales_no_cuentan(): void
    {
        Usuario::create([
            'nombre'         => 'Recepción',
            'perfil_id'      => Perfil::where('clave', 'recepcion')->value('id'),
            'es_profesional' => false,
            'pin'            => '1111',
        ]);

        $this->assertSame(2, LimitesPlan::profesionalesDisponibles(),
            'Quien no atiende clientas no ocupa plaza de profesional');
    }

    public function test_los_terminales_tambien_estan_limitados(): void
    {
        Terminal::create(['nombre' => 'Mostrador', 'codigo' => 'T001', 'activo' => true]);

        $this->assertSame(0, LimitesPlan::terminalesDisponibles());

        $this->expectException(\RuntimeException::class);

        LimitesPlan::comprobarTerminal();
    }

    public function test_un_plan_sin_limite_no_restringe(): void
    {
        $this->planBasico->update(['max_profesionales' => 0]);

        tenancy()->end();
        tenancy()->initialize($this->empresa->fresh());

        $this->assertNull(LimitesPlan::profesionalesDisponibles(),
            'Cero significa sin límite, no que no caben');

        LimitesPlan::comprobarProfesional();   // no debe lanzar
        $this->assertTrue(true);
    }

    public function test_las_funciones_del_plan_se_comprueban(): void
    {
        $this->assertTrue(LimitesPlan::incluye('reservas_online'));
        $this->assertFalse(LimitesPlan::incluye('pagos_online'));
        $this->assertFalse(LimitesPlan::incluye('verifactu'));
    }

    public function test_una_funcion_no_incluida_explica_como_conseguirla(): void
    {
        try {
            LimitesPlan::comprobarFuncion('pagos_online', 'El cobro de fianzas');
            $this->fail('Debería haber lanzado excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('El cobro de fianzas', $e->getMessage());
            $this->assertStringContainsString('cambiando de plan', $e->getMessage());
        }
    }

    public function test_bajar_de_plan_no_desactiva_a_nadie(): void
    {
        $this->crearProfesional('Marta');
        $this->crearProfesional('Lucía');

        // El salón baja a un plan de uno
        $this->planBasico->update(['max_profesionales' => 1]);

        tenancy()->end();
        tenancy()->initialize($this->empresa->fresh());

        $this->assertSame(2, Usuario::activos()->profesionales()->count(),
            'Desactivar profesionales por sorpresa dejaría citas huérfanas');
        $this->assertSame(0, LimitesPlan::profesionalesDisponibles());
    }

    // ------------------------------------------------------------------
    // Avisos al salón
    // ------------------------------------------------------------------

    public function test_el_primer_impago_avisa_sin_alarmar(): void
    {
        $this->limpiarCorreos();

        (new GestorSuscripciones())->registrarImpago($this->empresa);

        $asuntos = implode(' | ', $this->asuntosEnviados());

        $this->assertStringContainsString('No hemos podido cobrar', $asuntos);
        $this->assertStringNotContainsString('solo lectura', $asuntos,
            'Con un solo impago no se amenaza con cortar nada');
    }

    public function test_el_segundo_impago_avisa_de_la_suspension(): void
    {
        $gestor = new GestorSuscripciones();
        $gestor->registrarImpago($this->empresa);

        $this->limpiarCorreos();

        $gestor->registrarImpago($this->empresa->fresh());

        $this->assertStringContainsString('solo lectura',
            implode(' | ', $this->asuntosEnviados()));
    }

    public function test_los_avisos_al_salon_salen_siempre_por_el_smtp_de_la_plataforma(): void
    {
        // El salón tiene su propio servidor configurado
        $this->empresa->forceFill([
            'correo_propio' => true,
            'correo_host'   => 'smtp.delsalon.com',
        ])->save();

        (new CorreosPlataforma())->primerImpago($this->empresa->fresh());

        $this->assertNotSame('smtp.delsalon.com', config('mail.mailers.smtp.host'),
            'Un aviso de impago no puede salir del servidor del propio moroso');
    }

    public function test_sin_email_no_se_intenta_avisar(): void
    {
        // La columna es NOT NULL, asi que el caso real es cadena vacia
        $this->empresa->forceFill(['email' => ''])->save();

        $this->assertFalse((new CorreosPlataforma())->primerImpago($this->empresa->fresh()));
    }

    public function test_un_email_mal_escrito_tampoco_se_intenta(): void
    {
        $this->empresa->forceFill(['email' => 'esto-no-es-un-email'])->save();

        $this->assertFalse((new CorreosPlataforma())->primerImpago($this->empresa->fresh()));
    }
}
