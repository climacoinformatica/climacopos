<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\UsuarioHorario;
use App\Services\GestorAusencias;
use App\Services\GestorFichajes;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

/**
 * Cómo se comporta el registro de jornada cuando hay una ausencia.
 */
class FichajesAusenciasTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorFichajes $fichajes;
    protected GestorAusencias $ausencias;
    protected Usuario $marta;
    protected Usuario $jefa;
    protected Carbon $martes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-fa-' . uniqid(),
            'nombre_comercial' => 'Salón Ausencias y Fichajes',
            'email'            => 'fa@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->fichajes = new GestorFichajes();
        $this->ausencias = new GestorAusencias();

        $this->marta = Usuario::create([
            'nombre'    => 'Marta',
            'perfil_id' => Perfil::where('clave', 'profesional')->value('id'),
            'pin'       => '1234',
        ]);

        $this->jefa = Usuario::create([
            'nombre'    => 'Jefa',
            'perfil_id' => Perfil::where('clave', 'propietario')->value('id'),
            'pin'       => '9999',
        ]);

        foreach ([2, 3, 4, 5, 6] as $dia) {
            UsuarioHorario::create([
                'usuario_id' => $this->marta->id,
                'dia_semana' => $dia,
                'hora_ini'   => '09:00',
                'hora_fin'   => '18:00',
            ]);
        }

        // Un martes del mes en curso, para que caiga dentro del resumen
        $this->martes = now()->startOfMonth()->next(Carbon::TUESDAY);
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

    protected function aprobarVacaciones(Carbon $desde, Carbon $hasta): void
    {
        $ausencia = $this->ausencias->solicitar($this->marta, 'VACACIONES', $desde, $hasta);

        $this->ausencias->aprobar($ausencia, $this->jefa);
    }

    // ------------------------------------------------------------------

    public function test_un_dia_sin_fichar_es_incidencia_si_no_hay_ausencia(): void
    {
        $this->fichajes->fichar($this->marta, 'ENTRADA', 'TERMINAL',
            $this->martes->copy()->setTime(9, 0));

        // Entrada sin salida
        $jornada = $this->fichajes->jornada($this->marta, $this->martes);

        $this->assertTrue($jornada['incompleta']);
    }

    public function test_un_dia_de_vacaciones_no_es_incidencia(): void
    {
        $this->aprobarVacaciones($this->martes, $this->martes->copy()->addDays(4));

        $jornada = $this->fichajes->jornada($this->marta, $this->martes);

        $this->assertFalse($jornada['incompleta'],
            'Volver de vacaciones con quince días en rojo hace que nadie mire los avisos');
        $this->assertNotNull($jornada['ausencia']);
        $this->assertSame('Vacaciones', $jornada['ausencia']->etiqueta());
    }

    public function test_una_ausencia_pendiente_no_tapa_la_incidencia(): void
    {
        // Solicitada pero sin aprobar
        $this->ausencias->solicitar($this->marta, 'VACACIONES',
            $this->martes, $this->martes->copy()->addDays(4));

        $jornada = $this->fichajes->jornada($this->marta, $this->martes);

        $this->assertNull($jornada['ausencia'],
            'Hasta que se apruebe, esa persona debía haber fichado');
    }

    public function test_si_ficha_estando_de_vacaciones_se_cuenta_igual(): void
    {
        $this->aprobarVacaciones($this->martes, $this->martes->copy()->addDays(4));

        // Vino a cubrir una urgencia
        $this->fichajes->fichar($this->marta, 'ENTRADA', 'TERMINAL',
            $this->martes->copy()->setTime(10, 0));
        $this->fichajes->fichar($this->marta, 'SALIDA', 'TERMINAL',
            $this->martes->copy()->setTime(14, 0));

        $jornada = $this->fichajes->jornada($this->marta, $this->martes);

        $this->assertSame(240, $jornada['minutos'],
            'Si trabajó, esas horas existen aunque estuviera de vacaciones');
        $this->assertNotNull($jornada['ausencia']);
    }

    public function test_si_ficha_a_medias_estando_de_vacaciones_si_es_incidencia(): void
    {
        $this->aprobarVacaciones($this->martes, $this->martes->copy()->addDays(4));

        // Entró y no fichó la salida
        $this->fichajes->fichar($this->marta, 'ENTRADA', 'TERMINAL',
            $this->martes->copy()->setTime(10, 0));

        $jornada = $this->fichajes->jornada($this->marta, $this->martes);

        $this->assertTrue($jornada['incompleta'],
            'La ausencia solo justifica un día SIN fichajes, no uno a medias');
    }

    // ------------------------------------------------------------------

    public function test_el_resumen_mensual_cuenta_los_dias_de_ausencia(): void
    {
        $this->aprobarVacaciones($this->martes, $this->martes->copy()->addDays(4));

        $resumen = $this->fichajes->mes($this->marta, $this->martes->year, $this->martes->month);

        $this->assertSame(5, $resumen['dias_ausencia']);
        $this->assertSame(0, $resumen['dias_incompletos']);
        $this->assertSame(0, $resumen['dias_trabajados']);
    }

    public function test_el_resumen_distingue_ausencia_de_incidencia(): void
    {
        $this->aprobarVacaciones($this->martes, $this->martes->copy()->addDays(2));

        // Otro día, entrada sin salida
        $otroDia = $this->martes->copy()->addDays(7);

        $this->fichajes->fichar($this->marta, 'ENTRADA', 'TERMINAL',
            $otroDia->copy()->setTime(9, 0));

        $resumen = $this->fichajes->mes($this->marta, $this->martes->year, $this->martes->month);

        $this->assertSame(3, $resumen['dias_ausencia']);
        $this->assertSame(1, $resumen['dias_incompletos'],
            'Solo el día que falta un fichaje de verdad');
    }

    public function test_cada_dia_del_resumen_sabe_si_hubo_ausencia(): void
    {
        $this->aprobarVacaciones($this->martes, $this->martes->copy()->addDays(2));

        $resumen = $this->fichajes->mes($this->marta, $this->martes->year, $this->martes->month);

        $dia = collect($resumen['dias'])
            ->first(fn ($d) => $d['fecha']->isSameDay($this->martes));

        $this->assertNotNull($dia['ausencia']);
        $this->assertSame('VACACIONES', $dia['ausencia']->tipo);
    }
}
