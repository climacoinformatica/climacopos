<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\ConfigPlataforma;
use App\Models\CorreoEnviado;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\UsuarioHorario;
use App\Services\Correo\ConfiguradorCorreo;
use App\Services\Correo\GestorCorreos;
use App\Services\GestorReservas;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class CorreosTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected Usuario $marta;
    protected Articulo $corte;
    protected Carbon $lunes;

    protected function setUp(): void
    {
        parent::setUp();

        // SMTP de la plataforma configurado
        ConfigPlataforma::guardar('correo_host', 'smtp.ejemplo.com');
        ConfigPlataforma::guardar('correo_puerto', 587);
        ConfigPlataforma::guardar('correo_usuario', 'avisos@ejemplo.com');
        ConfigPlataforma::guardar('correo_password', 'secreto');
        ConfigPlataforma::guardar('correo_remitente', 'no-responder@ejemplo.com');

        $this->empresa = Empresa::create([
            'slug'             => 'test-mail-' . uniqid(),
            'nombre_comercial' => 'Peluquería Correo',
            'email'            => 'salon@ejemplo.com',
            'telefono'         => '922000000',
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

        $familia = Familia::create(['nombre' => 'Corte', 'tipo' => 'SERVICIO']);

        $this->corte = Articulo::create([
            'familia_id'   => $familia->id,
            'tipo'         => 'SERVICIO',
            'nombre'       => 'Corte',
            'precio'       => 22.00,
            'impuesto_pct' => 7.00,
            'duracion_min' => 30,
        ]);
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

        parent::tearDown();
    }

    protected function reservar(string $email = 'lucia@ejemplo.com'): Reserva
    {
        return (new GestorReservas())->crear(
            $this->lunes,
            '10:00',
            [['articulo_id' => $this->corte->id, 'usuario_id' => $this->marta->id]],
            ['nombre' => 'Lucía', 'telefono' => '600' . random_int(100000, 999999),
             'email' => $email, 'acepta_rgpd' => true],
            origen: 'ONLINE',
        );
    }

    // ------------------------------------------------------------------
    // Configuración
    // ------------------------------------------------------------------

    public function test_se_usa_el_smtp_de_la_plataforma_por_defecto(): void
    {
        (new ConfiguradorCorreo())->preparar();

        $this->assertSame('smtp.ejemplo.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
    }

    public function test_el_nombre_del_remitente_es_el_del_salon(): void
    {
        $remitente = (new ConfiguradorCorreo())->preparar();

        $this->assertSame('Peluquería Correo', $remitente['nombre'],
            'La clienta reconoce a su peluquería, no a la plataforma');
    }

    public function test_un_salon_puede_tener_su_propio_smtp(): void
    {
        $this->empresa->forceFill([
            'correo_propio'     => true,
            'correo_host'       => 'smtp.mipeluqueria.com',
            'correo_puerto'     => 465,
            'correo_usuario'    => 'citas@mipeluqueria.com',
            'correo_password'   => Crypt::encryptString('clave-propia'),
            'correo_cifrado'    => 'ssl',
            'correo_remitente'  => 'citas@mipeluqueria.com',
        ])->save();

        tenancy()->end();
        tenancy()->initialize($this->empresa->fresh());

        $remitente = (new ConfiguradorCorreo())->preparar();

        $this->assertSame('smtp.mipeluqueria.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('citas@mipeluqueria.com', $remitente['email']);
    }

    public function test_la_contrasena_del_salon_se_guarda_cifrada(): void
    {
        $this->empresa->forceFill([
            'correo_password' => Crypt::encryptString('clave-secreta'),
        ])->save();

        $guardada = \Illuminate\Support\Facades\DB::connection('mysql')
            ->table('empresas')->where('id', $this->empresa->id)->value('correo_password');

        $this->assertNotSame('clave-secreta', $guardada);
        $this->assertSame('clave-secreta', Crypt::decryptString($guardada));
    }

    public function test_sin_configuracion_no_se_puede_enviar(): void
    {
        ConfigPlataforma::query()->delete();

        $this->assertFalse((new ConfiguradorCorreo())->disponible());
    }

    public function test_el_cifrado_ninguno_se_traduce_a_nulo(): void
    {
        ConfigPlataforma::guardar('correo_cifrado', 'ninguno');

        (new ConfiguradorCorreo())->preparar();

        $this->assertNull(config('mail.mailers.smtp.encryption'));
    }

    // ------------------------------------------------------------------
    // Envíos
    // ------------------------------------------------------------------

    public function test_una_reserva_confirmada_genera_correo(): void
    {
        Mail::fake();

        $reserva = $this->reservar();
        $enviado = (new GestorCorreos())->reservaConfirmada($reserva);

        $this->assertTrue($enviado);
        $this->assertSame(1, CorreoEnviado::where('tipo', 'RESERVA_CONFIRMADA')->count());
    }

    public function test_sin_email_no_se_intenta_enviar(): void
    {
        Mail::fake();

        $reserva = $this->reservar();
        $reserva->update(['cliente_email' => null]);

        $this->assertFalse((new GestorCorreos())->reservaConfirmada($reserva->fresh()));
        $this->assertSame(0, CorreoEnviado::count());
    }

    public function test_un_email_mal_escrito_no_se_intenta_enviar(): void
    {
        Mail::fake();

        $reserva = $this->reservar();
        $reserva->update(['cliente_email' => 'esto-no-es-un-email']);

        $this->assertFalse((new GestorCorreos())->reservaConfirmada($reserva->fresh()));
    }

    public function test_el_recordatorio_no_se_envia_dos_veces(): void
    {
        Mail::fake();

        $reserva = $this->reservar();
        $correos = new GestorCorreos();

        $this->assertTrue($correos->recordatorio($reserva));
        $this->assertFalse($correos->recordatorio($reserva),
            'Recibir dos recordatorios de la misma cita queda descuidado');

        $this->assertSame(1, CorreoEnviado::where('tipo', 'RECORDATORIO')->count());
    }

    public function test_todos_los_envios_quedan_registrados(): void
    {
        Mail::fake();

        $reserva = $this->reservar();
        (new GestorCorreos())->reservaConfirmada($reserva);

        $registro = CorreoEnviado::first();

        $this->assertSame('lucia@ejemplo.com', $registro->destinatario);
        $this->assertSame('ENVIADO', $registro->estado);
        $this->assertSame($reserva->id, $registro->referencia_id);
        $this->assertNotNull($registro->enviado_en);
    }

    public function test_sin_servidor_configurado_se_registra_el_intento(): void
    {
        ConfigPlataforma::query()->delete();

        $reserva = $this->reservar();
        $enviado = (new GestorCorreos())->reservaConfirmada($reserva);

        $this->assertFalse($enviado);

        $registro = CorreoEnviado::first();
        $this->assertSame('SIN_CONFIGURAR', $registro->estado,
            'Que no haya servidor no puede pasar desapercibido');
    }

    public function test_un_fallo_de_correo_no_lanza_excepcion(): void
    {
        ConfigPlataforma::guardar('correo_host', 'servidor.que.no.existe.invalido');

        $reserva = $this->reservar();

        // No debe lanzar: un fallo de correo no puede tumbar una reserva
        $enviado = (new GestorCorreos())->reservaConfirmada($reserva);

        $this->assertFalse($enviado);
    }

    // ------------------------------------------------------------------
    // Limpieza
    // ------------------------------------------------------------------

    public function test_los_registros_antiguos_se_pueden_purgar(): void
    {
        Mail::fake();

        $reserva = $this->reservar();
        (new GestorCorreos())->reservaConfirmada($reserva);

        CorreoEnviado::query()->update(['enviado_en' => now()->subYear()]);

        $this->assertSame(1, CorreoEnviado::purgar(6));
        $this->assertSame(0, CorreoEnviado::count());
    }
}
