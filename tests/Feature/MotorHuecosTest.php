<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\BloqueoAgenda;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\ReservaTemporal;
use App\Models\Usuario;
use App\Models\UsuarioExcepcion;
use App\Models\UsuarioHorario;
use App\Services\GestorReservas;
use App\Services\MotorHuecos;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class MotorHuecosTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected MotorHuecos $motor;
    protected Usuario $marta;
    protected Familia $familia;
    protected Carbon $lunes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-agenda-' . uniqid(),
            'nombre_comercial' => 'Salon Agenda',
            'email'            => 'a@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new PerfilesSeeder())->run();

        $this->motor   = new MotorHuecos();
        $this->familia = Familia::create(['nombre' => 'Pruebas', 'tipo' => 'SERVICIO']);

        // Un lunes futuro, para que la antelación mínima no estorbe
        $this->lunes = Carbon::now()->addWeek()->startOfWeek(Carbon::MONDAY);

        $this->marta = $this->crearProfesional('Marta');
        $this->darHorario($this->marta, 1, '09:00', '13:00');   // lunes de 9 a 13
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
    // Ayudantes
    // ------------------------------------------------------------------

    protected function crearProfesional(string $nombre): Usuario
    {
        return Usuario::create([
            'nombre'         => $nombre,
            'perfil_id'      => Perfil::where('clave', 'profesional')->value('id'),
            'es_profesional' => true,
            'pin'            => '1234',
            'estado'         => 'ACTIVO',
        ]);
    }

    protected function darHorario(Usuario $usuario, int $dia, string $ini, string $fin): void
    {
        UsuarioHorario::create([
            'usuario_id' => $usuario->id,
            'dia_semana' => $dia,
            'hora_ini'   => $ini,
            'hora_fin'   => $fin,
        ]);
    }

    protected function crearServicio(string $nombre, int $duracion, int $pausa = 0, int $final = 0, array $extra = []): Articulo
    {
        return Articulo::create(array_merge([
            'familia_id'       => $this->familia->id,
            'tipo'             => 'SERVICIO',
            'nombre'           => $nombre,
            'precio'           => 30.00,
            'impuesto_pct'     => 7.00,
            'duracion_min'     => $duracion,
            'tiempo_pausa_min' => $pausa,
            'tiempo_final_min' => $final,
        ], $extra));
    }

    protected function crearCita(Articulo $servicio, string $hora, ?Usuario $profesional = null, string $estado = 'CONFIRMADA'): Reserva
    {
        $reserva = (new GestorReservas())->crear(
            $this->lunes,
            $hora,
            [['articulo_id' => $servicio->id, 'usuario_id' => ($profesional ?? $this->marta)->id]],
            ['nombre' => 'Cliente prueba', 'telefono' => '600' . random_int(100000, 999999)],
        );

        if ($estado !== 'CONFIRMADA') {
            $reserva->update(['estado' => $estado]);
        }

        return $reserva;
    }

    // ------------------------------------------------------------------
    // Horario básico
    // ------------------------------------------------------------------

    public function test_sin_horario_no_hay_huecos(): void
    {
        $servicio = $this->crearServicio('Corte', 30);
        $martes = $this->lunes->copy()->addDay();   // no tiene horario el martes

        $this->assertSame([], $this->motor->huecosDe($martes, $servicio, $this->marta));
    }

    public function test_la_jornada_se_trocea_en_pasos_de_quince_minutos(): void
    {
        $servicio = $this->crearServicio('Corte', 30);

        $huecos = $this->motor->huecosDe($this->lunes, $servicio, $this->marta);

        $this->assertSame('09:00', $huecos[0]);
        $this->assertSame('09:15', $huecos[1]);

        // Último hueco: debe caber entero antes de las 13:00
        $this->assertSame('12:30', end($huecos));
    }

    public function test_un_servicio_largo_reduce_el_ultimo_hueco(): void
    {
        $largo = $this->crearServicio('Mechas', 120);

        $huecos = $this->motor->huecosDe($this->lunes, $largo, $this->marta);

        $this->assertSame('11:00', end($huecos));
    }

    public function test_un_servicio_mas_largo_que_la_jornada_no_da_huecos(): void
    {
        $imposible = $this->crearServicio('Maratón', 300);

        $this->assertSame([], $this->motor->huecosDe($this->lunes, $imposible, $this->marta));
    }

    public function test_la_jornada_partida_genera_dos_bloques(): void
    {
        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');
        $this->darHorario($ana, 1, '16:00', '20:00');

        $servicio = $this->crearServicio('Corte', 60);
        $huecos = $this->motor->huecosDe($this->lunes, $servicio, $ana);

        $this->assertContains('09:00', $huecos);
        $this->assertContains('12:00', $huecos);
        $this->assertNotContains('13:00', $huecos, 'No debe haber huecos en la comida');
        $this->assertNotContains('14:00', $huecos);
        $this->assertContains('16:00', $huecos);
        $this->assertContains('19:00', $huecos);
    }

    // ------------------------------------------------------------------
    // Citas existentes
    // ------------------------------------------------------------------

    public function test_una_cita_bloquea_su_franja(): void
    {
        $servicio = $this->crearServicio('Corte', 60);
        $this->crearCita($servicio, '10:00');

        $huecos = $this->motor->huecosDe($this->lunes, $servicio, $this->marta);

        $this->assertNotContains('10:00', $huecos);
        $this->assertNotContains('10:30', $huecos);
        $this->assertNotContains('09:30', $huecos, 'Empezar a las 9:30 chocaría con la cita');
        $this->assertContains('09:00', $huecos);
        $this->assertContains('11:00', $huecos, 'Justo al terminar debe estar libre');
    }

    public function test_tocarse_en_el_extremo_no_es_solapar(): void
    {
        $servicio = $this->crearServicio('Corte', 60);
        $this->crearCita($servicio, '10:00');   // 10:00-11:00

        $this->assertTrue(
            $this->motor->estaLibre($this->lunes, '11:00', $servicio, $this->marta),
            'Una cita que empieza cuando acaba otra debe caber'
        );
    }

    public function test_las_citas_canceladas_liberan_el_hueco(): void
    {
        $servicio = $this->crearServicio('Corte', 60);
        $cita = $this->crearCita($servicio, '10:00');

        $this->assertNotContains('10:00', $this->motor->huecosDe($this->lunes, $servicio, $this->marta));

        $cita->cancelar();

        $this->assertContains('10:00', $this->motor->huecosDe($this->lunes, $servicio, $this->marta));
    }

    public function test_las_reservas_pendientes_tambien_ocupan(): void
    {
        $servicio = $this->crearServicio('Corte', 60);
        $this->crearCita($servicio, '10:00', estado: 'PENDIENTE');

        $this->assertNotContains('10:00', $this->motor->huecosDe($this->lunes, $servicio, $this->marta),
            'Una reserva pendiente de confirmar debe retener el hueco, o dos clientes reservarían lo mismo');
    }

    // ------------------------------------------------------------------
    // Pausa intermedia: el corazón del negocio
    // ------------------------------------------------------------------

    public function test_durante_la_pausa_el_profesional_queda_libre(): void
    {
        // Tinte: 20' aplicando + 30' esperando + 15' lavando = 65' en agenda
        $tinte = $this->crearServicio('Tinte', 20, 30, 15);
        $corte = $this->crearServicio('Corte', 30);

        $this->crearCita($tinte, '09:00');
        // Ocupado 09:00-09:20 y 09:50-10:05. Libre 09:20-09:50.

        $this->assertTrue(
            $this->motor->estaLibre($this->lunes, '09:20', $corte, $this->marta),
            'Durante la espera del tinte se debe poder meter un corte'
        );
    }

    public function test_un_servicio_que_no_cabe_en_la_pausa_se_rechaza(): void
    {
        $tinte = $this->crearServicio('Tinte', 20, 30, 15);
        $largo = $this->crearServicio('Corte largo', 45);

        $this->crearCita($tinte, '09:00');

        $this->assertFalse(
            $this->motor->estaLibre($this->lunes, '09:20', $largo, $this->marta),
            'Un corte de 45 min no cabe en una pausa de 30'
        );
    }

    public function test_los_tramos_activos_se_parten_cuando_hay_pausa(): void
    {
        $tramos = $this->motor->tramosActivos(540, 20, 30, 15);   // 09:00

        $this->assertCount(2, $tramos);
        $this->assertSame('09:00-09:20', (string) $tramos[0]);
        $this->assertSame('09:50-10:05', (string) $tramos[1]);
    }

    public function test_sin_pausa_hay_un_solo_tramo_continuo(): void
    {
        $tramos = $this->motor->tramosActivos(540, 30, 0, 10);

        $this->assertCount(1, $tramos);
        $this->assertSame('09:00-09:40', (string) $tramos[0]);
    }

    public function test_el_trabajo_final_debe_caber_dentro_de_la_jornada(): void
    {
        $tinte = $this->crearServicio('Tinte', 20, 30, 15);

        $huecos = $this->motor->huecosDe($this->lunes, $tinte, $this->marta);

        // 11:55 + 65 min = 13:00 exactas, cabe. 12:00 se pasaría.
        $this->assertNotContains('12:00', $huecos);
        $this->assertContains('11:45', $huecos);
    }

    // ------------------------------------------------------------------
    // Excepciones
    // ------------------------------------------------------------------

    public function test_las_vacaciones_anulan_el_dia_entero(): void
    {
        UsuarioExcepcion::create([
            'usuario_id' => $this->marta->id,
            'fecha_ini'  => $this->lunes->toDateString(),
            'fecha_fin'  => $this->lunes->copy()->addDays(6)->toDateString(),
            'tipo'       => 'VACACIONES',
        ]);

        $servicio = $this->crearServicio('Corte', 30);

        $this->assertSame([], $this->motor->huecosDe($this->lunes, $servicio, $this->marta));
    }

    public function test_un_festivo_de_empresa_afecta_a_todos(): void
    {
        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');

        UsuarioExcepcion::create([
            'usuario_id' => null,               // toda la empresa
            'fecha_ini'  => $this->lunes->toDateString(),
            'fecha_fin'  => $this->lunes->toDateString(),
            'tipo'       => 'FESTIVO',
            'motivo'     => 'Día de Canarias',
        ]);

        $servicio = $this->crearServicio('Corte', 30);

        $this->assertSame([], $this->motor->huecos($this->lunes, $servicio));
    }

    public function test_un_horario_especial_sustituye_al_habitual(): void
    {
        UsuarioExcepcion::create([
            'usuario_id' => $this->marta->id,
            'fecha_ini'  => $this->lunes->toDateString(),
            'fecha_fin'  => $this->lunes->toDateString(),
            'tipo'       => 'HORARIO_ESPECIAL',
            'hora_ini'   => '10:00',
            'hora_fin'   => '12:00',
        ]);

        $servicio = $this->crearServicio('Corte', 30);
        $huecos = $this->motor->huecosDe($this->lunes, $servicio, $this->marta);

        $this->assertSame('10:00', $huecos[0]);
        $this->assertSame('11:30', end($huecos));
    }

    // ------------------------------------------------------------------
    // Bloqueos
    // ------------------------------------------------------------------

    public function test_un_bloqueo_manual_quita_huecos(): void
    {
        BloqueoAgenda::create([
            'usuario_id' => $this->marta->id,
            'fecha'      => $this->lunes->toDateString(),
            'hora_ini'   => '11:00',
            'hora_fin'   => '12:00',
            'motivo'     => 'Reunión',
        ]);

        $servicio = $this->crearServicio('Corte', 30);
        $huecos = $this->motor->huecosDe($this->lunes, $servicio, $this->marta);

        $this->assertNotContains('11:00', $huecos);
        $this->assertNotContains('10:45', $huecos);
        $this->assertContains('12:00', $huecos);
    }

    public function test_un_bloqueo_general_afecta_a_todo_el_salon(): void
    {
        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');

        BloqueoAgenda::create([
            'usuario_id' => null,
            'fecha'      => $this->lunes->toDateString(),
            'hora_ini'   => '09:00',
            'hora_fin'   => '13:00',
            'motivo'     => 'Formación del equipo',
        ]);

        $servicio = $this->crearServicio('Corte', 30);

        $this->assertSame([], $this->motor->huecos($this->lunes, $servicio));
    }

    // ------------------------------------------------------------------
    // Retenciones temporales
    // ------------------------------------------------------------------

    public function test_una_retencion_vigente_bloquea_el_hueco(): void
    {
        ReservaTemporal::create([
            'usuario_id' => $this->marta->id,
            'fecha'      => $this->lunes->toDateString(),
            'hora_ini'   => '10:00',
            'hora_fin'   => '11:00',
        ]);

        $servicio = $this->crearServicio('Corte', 30);

        $this->assertNotContains('10:00', $this->motor->huecosDe($this->lunes, $servicio, $this->marta),
            'Mientras alguien paga, su hueco no se ofrece a otro cliente');
    }

    public function test_una_retencion_caducada_libera_el_hueco(): void
    {
        ReservaTemporal::create([
            'usuario_id' => $this->marta->id,
            'fecha'      => $this->lunes->toDateString(),
            'hora_ini'   => '10:00',
            'hora_fin'   => '11:00',
            'caduca_en'  => now()->subMinutes(5),
        ]);

        $servicio = $this->crearServicio('Corte', 30);

        $this->assertContains('10:00', $this->motor->huecosDe($this->lunes, $servicio, $this->marta));
    }

    // ------------------------------------------------------------------
    // Recursos limitados
    // ------------------------------------------------------------------

    public function test_un_recurso_unico_impide_dos_citas_simultaneas(): void
    {
        $cabina = Recurso::create(['nombre' => 'Cabina', 'cantidad' => 1]);

        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');

        $masaje = $this->crearServicio('Masaje', 60, extra: ['recurso_id' => $cabina->id]);

        $this->crearCita($masaje, '10:00', $this->marta);

        // Ana está libre, pero la única cabina está ocupada
        $this->assertNotContains('10:00', $this->motor->huecosDe($this->lunes, $masaje, $ana));
        $this->assertContains('11:00', $this->motor->huecosDe($this->lunes, $masaje, $ana));
    }

    public function test_dos_unidades_de_recurso_admiten_dos_citas(): void
    {
        $cabina = Recurso::create(['nombre' => 'Cabinas', 'cantidad' => 2]);

        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');

        $masaje = $this->crearServicio('Masaje', 60, extra: ['recurso_id' => $cabina->id]);

        $this->crearCita($masaje, '10:00', $this->marta);

        $this->assertContains('10:00', $this->motor->huecosDe($this->lunes, $masaje, $ana));
    }

    public function test_la_cabina_sigue_ocupada_durante_la_pausa(): void
    {
        // A diferencia del profesional, el recurso NO se libera en la pausa
        $cabina = Recurso::create(['nombre' => 'Cabina', 'cantidad' => 1]);

        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');

        $tratamiento = $this->crearServicio('Tratamiento', 20, 30, 15, ['recurso_id' => $cabina->id]);

        $this->crearCita($tratamiento, '09:00', $this->marta);

        $this->assertFalse(
            $this->motor->estaLibre($this->lunes, '09:20', $tratamiento, $ana),
            'La cabina está ocupada aunque la profesional se haya ido a atender a otra clienta'
        );
    }

    // ------------------------------------------------------------------
    // Varios profesionales
    // ------------------------------------------------------------------

    public function test_el_servicio_sin_profesionales_asignados_lo_hace_cualquiera(): void
    {
        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '15:00', '19:00');

        $servicio = $this->crearServicio('Corte', 30);
        $huecos = $this->motor->huecos($this->lunes, $servicio);

        $this->assertContains('09:00', $huecos, 'Horario de Marta');
        $this->assertContains('15:00', $huecos, 'Horario de Ana');
    }

    public function test_el_servicio_con_profesionales_asignados_solo_ofrece_los_suyos(): void
    {
        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '15:00', '19:00');

        $servicio = $this->crearServicio('Balayage', 60);
        $servicio->profesionales()->attach($ana->id);

        $huecos = $this->motor->huecos($this->lunes, $servicio);

        $this->assertNotContains('09:00', $huecos, 'Marta no hace este servicio');
        $this->assertContains('15:00', $huecos);
    }

    public function test_los_huecos_indican_que_profesional_los_atiende(): void
    {
        $ana = $this->crearProfesional('Ana');
        $this->darHorario($ana, 1, '09:00', '13:00');

        $servicio = $this->crearServicio('Corte', 30);
        $mapa = $this->motor->huecosConProfesional($this->lunes, $servicio);

        $this->assertCount(2, $mapa['09:00'], 'A las 9:00 están libres las dos');

        $this->crearCita($servicio, '09:00', $this->marta);
        $mapa = $this->motor->huecosConProfesional($this->lunes, $servicio);

        $this->assertSame([$ana->id], $mapa['09:00']);
    }

    // ------------------------------------------------------------------
    // Creación de citas
    // ------------------------------------------------------------------

    public function test_se_encadenan_varios_servicios_en_una_cita(): void
    {
        $corte = $this->crearServicio('Corte', 30);
        $tinte = $this->crearServicio('Tinte', 20, 30, 15);

        $reserva = (new GestorReservas())->crear(
            $this->lunes,
            '09:00',
            [
                ['articulo_id' => $corte->id, 'usuario_id' => $this->marta->id],
                ['articulo_id' => $tinte->id, 'usuario_id' => $this->marta->id],
            ],
            ['nombre' => 'Ana Cliente', 'telefono' => '600111222'],
        );

        $this->assertCount(2, $reserva->lineas);
        $this->assertSame('09:00', substr($reserva->lineas[0]->hora_ini, 0, 5));
        $this->assertSame('09:30', substr($reserva->lineas[1]->hora_ini, 0, 5));
        $this->assertSame('10:35', substr($reserva->hora_fin, 0, 5));
    }

    public function test_no_se_permite_crear_una_cita_sobre_otra(): void
    {
        $servicio = $this->crearServicio('Corte', 60);
        $this->crearCita($servicio, '10:00');

        $this->expectException(\RuntimeException::class);

        $this->crearCita($servicio, '10:30');
    }

    public function test_el_cliente_se_reutiliza_por_telefono(): void
    {
        $servicio = $this->crearServicio('Corte', 30);
        $gestor = new GestorReservas();

        $primera = $gestor->crear($this->lunes, '09:00',
            [['articulo_id' => $servicio->id, 'usuario_id' => $this->marta->id]],
            ['nombre' => 'Lucía', 'telefono' => '600 123 456']);

        $segunda = $gestor->crear($this->lunes, '10:00',
            [['articulo_id' => $servicio->id, 'usuario_id' => $this->marta->id]],
            ['nombre' => 'Lucia', 'telefono' => '600123456']);

        $this->assertSame($primera->cliente_id, $segunda->cliente_id,
            'El mismo teléfono con distinto formato debe ser el mismo cliente');
    }

    public function test_el_codigo_de_reserva_es_legible_y_unico(): void
    {
        $servicio = $this->crearServicio('Corte', 30);

        $primera = $this->crearCita($servicio, '09:00');
        $segunda = $this->crearCita($servicio, '10:00');

        $this->assertMatchesRegularExpression('/^RS-[A-Z2-9]{5}$/', $primera->codigo);
        $this->assertNotSame($primera->codigo, $segunda->codigo);
        $this->assertStringNotContainsString('0', $primera->codigo, 'Sin ceros: se confunden con la O al dictarlos');
    }

    public function test_mover_una_cita_no_choca_consigo_misma(): void
    {
        $servicio = $this->crearServicio('Corte', 60);
        $cita = $this->crearCita($servicio, '10:00');

        // Mover 15 minutos: los tramos nuevo y viejo se solapan
        $movida = (new GestorReservas())->mover($cita, $this->lunes, '10:15');

        $this->assertSame('10:15', substr($movida->hora_ini, 0, 5));
    }

    // ------------------------------------------------------------------
    // Transiciones de estado
    // ------------------------------------------------------------------

    public function test_solo_se_confirma_lo_que_esta_pendiente(): void
    {
        $servicio = $this->crearServicio('Corte', 30);
        $cita = $this->crearCita($servicio, '09:00', estado: 'PENDIENTE');

        $this->assertTrue($cita->confirmar($this->marta));
        $this->assertSame('CONFIRMADA', $cita->fresh()->estado);

        $this->assertFalse($cita->fresh()->confirmar($this->marta), 'No se confirma dos veces');
    }

    public function test_un_no_show_incrementa_el_contador_del_cliente(): void
    {
        $servicio = $this->crearServicio('Corte', 30);
        $cita = $this->crearCita($servicio, '09:00');

        $cita->marcarNoShow();

        $this->assertSame(1, $cita->cliente->fresh()->no_shows);
        $this->assertSame('NO_SHOW', $cita->fresh()->estado);
    }

    public function test_una_cita_cerrada_no_se_puede_mover(): void
    {
        $servicio = $this->crearServicio('Corte', 30);
        $cita = $this->crearCita($servicio, '09:00');
        $cita->marcarAtendida();

        $this->expectException(\RuntimeException::class);

        (new GestorReservas())->mover($cita->fresh(), $this->lunes, '11:00');
    }
}
