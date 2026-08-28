<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\UsuarioExcepcion;
use App\Models\UsuarioHorario;
use App\Services\GestorAusencias;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class AusenciasTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorAusencias $gestor;
    protected Usuario $marta;
    protected Usuario $jefa;
    protected Carbon $lunes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-aus-' . uniqid(),
            'nombre_comercial' => 'Salón Ausencias',
            'email'            => 'aus@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->gestor = new GestorAusencias();

        $this->marta = Usuario::create([
            'nombre'         => 'Marta',
            'perfil_id'      => Perfil::where('clave', 'profesional')->value('id'),
            'es_profesional' => true,
            'dias_vacaciones'=> 22,
            'pin'            => '1234',
        ]);

        $this->jefa = Usuario::create([
            'nombre'    => 'Jefa',
            'perfil_id' => Perfil::where('clave', 'propietario')->value('id'),
            'pin'       => '9999',
        ]);

        // Marta trabaja de martes a sábado. El salón cierra los lunes.
        foreach ([2, 3, 4, 5, 6] as $dia) {
            UsuarioHorario::create([
                'usuario_id' => $this->marta->id,
                'dia_semana' => $dia,
                'hora_ini'   => '09:00',
                'hora_fin'   => '18:00',
            ]);
        }

        $this->lunes = Carbon::now()->addMonth()->startOfMonth()->next(Carbon::MONDAY);
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
    // Cómputo de días
    // ------------------------------------------------------------------

    public function test_no_se_cuentan_los_dias_que_el_salon_cierra(): void
    {
        // De lunes a domingo: siete días naturales, pero Marta libra
        // los lunes y los domingos, así que son cinco de vacaciones
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->assertEqualsWithDelta(5.0, (float) $ausencia->dias_computados, 0.01,
            'Contar los días de cierre sería robarle días a la trabajadora');
        $this->assertSame(7, $ausencia->diasNaturales());
    }

    public function test_una_baja_no_consume_dias_de_vacaciones(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'BAJA',
            $this->lunes, $this->lunes->copy()->addDays(10),
        );

        $this->assertEqualsWithDelta(0.0, (float) $ausencia->dias_computados, 0.01,
            'Descontar una baja del cupo sería un error con consecuencias laborales');
        $this->assertFalse($ausencia->consumeCupo());
    }

    public function test_un_medio_dia_cuenta_medio(): void
    {
        $martes = $this->lunes->copy()->addDay();

        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES', $martes, $martes, null, 'MANANA',
        );

        $this->assertEqualsWithDelta(0.5, (float) $ausencia->dias_computados, 0.01);
        $this->assertTrue($ausencia->esMedioDia());
    }

    public function test_un_medio_dia_no_puede_abarcar_varios_dias(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('una única jornada');

        $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(3), null, 'MANANA',
        );
    }

    public function test_la_fecha_final_no_puede_ser_anterior(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->subDays(3),
        );
    }

    // ------------------------------------------------------------------
    // Solapamientos
    // ------------------------------------------------------------------

    public function test_no_se_solapan_dos_ausencias_de_la_misma_persona(): void
    {
        $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ya hay una ausencia');

        $this->gestor->solicitar(
            $this->marta, 'ASUNTOS_PROPIOS',
            $this->lunes->copy()->addDays(3), $this->lunes->copy()->addDays(8),
        );
    }

    public function test_una_ausencia_cancelada_libera_las_fechas(): void
    {
        $primera = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->gestor->cancelar($primera, $this->jefa);

        // Ahora sí se puede pedir en esas mismas fechas
        $segunda = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->assertSame('SOLICITADA', $segunda->estado);
    }

    // ------------------------------------------------------------------
    // Aprobación y agenda
    // ------------------------------------------------------------------

    public function test_aprobar_crea_la_excepcion_que_bloquea_la_agenda(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->assertSame(0, UsuarioExcepcion::count(),
            'Mientras está pendiente, la agenda sigue ofreciendo huecos');

        $this->gestor->aprobar($ausencia, $this->jefa);

        $this->assertSame(1, UsuarioExcepcion::count(),
            'Al aprobar, el motor de huecos deja de ofrecer esos días');

        $excepcion = UsuarioExcepcion::first();

        $this->assertSame($this->marta->id, $excepcion->usuario_id);
        $this->assertSame('VACACIONES', $excepcion->tipo);
    }

    public function test_cancelar_una_aprobada_retira_la_excepcion(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->gestor->aprobar($ausencia, $this->jefa);
        $this->assertSame(1, UsuarioExcepcion::count());

        $this->gestor->cancelar($ausencia->fresh(), $this->jefa, 'Cambio de planes');

        $this->assertSame(0, UsuarioExcepcion::count(),
            'Esos días vuelven a estar disponibles para reservar');
    }

    public function test_rechazar_exige_explicacion(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(3),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('por qué se rechaza');

        $this->gestor->rechazar($ausencia, $this->jefa, '   ');
    }

    public function test_una_rechazada_no_bloquea_la_agenda(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(3),
        );

        $this->gestor->rechazar($ausencia, $this->jefa, 'Coincide con temporada alta');

        $this->assertSame(0, UsuarioExcepcion::count());
        $this->assertSame('RECHAZADA', $ausencia->fresh()->estado);
    }

    // ------------------------------------------------------------------
    // Cupo
    // ------------------------------------------------------------------

    public function test_el_cupo_descuenta_solo_lo_aprobado(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),   // 5 días
        );

        $cupo = $this->gestor->cupo($this->marta, $this->lunes->year);

        $this->assertEqualsWithDelta(22.0, $cupo['restantes'], 0.01,
            'Lo pendiente todavía no se ha disfrutado');
        $this->assertEqualsWithDelta(5.0, $cupo['solicitados'], 0.01);
        $this->assertEqualsWithDelta(17.0, $cupo['proyectado'], 0.01);

        $this->gestor->aprobar($ausencia, $this->jefa);

        $cupo = $this->gestor->cupo($this->marta, $this->lunes->year);

        $this->assertEqualsWithDelta(17.0, $cupo['restantes'], 0.01);
    }

    public function test_las_bajas_no_tocan_el_cupo(): void
    {
        $baja = $this->gestor->solicitar(
            $this->marta, 'BAJA',
            $this->lunes, $this->lunes->copy()->addDays(20),
        );

        $this->gestor->aprobar($baja, $this->jefa);

        $this->assertEqualsWithDelta(22.0,
            $this->gestor->cupo($this->marta, $this->lunes->year)['restantes'], 0.01);
    }

    public function test_se_puede_pedir_aunque_no_queden_dias(): void
    {
        $this->marta->update(['dias_vacaciones' => 1]);

        $ausencia = $this->gestor->solicitar(
            $this->marta->fresh(), 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->assertSame('SOLICITADA', $ausencia->estado,
            'Puede haber acuerdos particulares o días del año anterior: '
            . 'quien aprueba decide, el software no lo impide');
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public function test_se_sabe_si_alguien_esta_ausente_un_dia(): void
    {
        $ausencia = $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->gestor->aprobar($ausencia, $this->jefa);

        $enMedio = $this->lunes->copy()->addDays(3);

        $this->assertNotNull($this->gestor->estaAusente($this->marta, $enMedio));
        $this->assertNull($this->gestor->estaAusente(
            $this->marta, $this->lunes->copy()->subDay()
        ));
    }

    public function test_una_pendiente_no_cuenta_como_ausencia(): void
    {
        $this->gestor->solicitar(
            $this->marta, 'VACACIONES',
            $this->lunes, $this->lunes->copy()->addDays(6),
        );

        $this->assertNull($this->gestor->estaAusente($this->marta, $this->lunes),
            'Hasta que se apruebe, esa persona trabaja');
    }

    public function test_el_calendario_detecta_dias_con_dos_personas_fuera(): void
    {
        foreach ([$this->marta, $this->jefa] as $persona) {
            $ausencia = $this->gestor->solicitar(
                $persona, 'VACACIONES',
                $this->lunes, $this->lunes->copy()->addDays(4),
            );

            $this->gestor->aprobar($ausencia, $this->jefa);
        }

        $calendario = $this->gestor->calendario($this->lunes->year, $this->lunes->month);

        $this->assertNotEmpty($calendario['solapes'],
            'Dejar el salón sin nadie es el error caro que hay que ver antes de aprobar');
    }
}
