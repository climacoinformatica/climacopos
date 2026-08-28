<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Festivo;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\UsuarioExcepcion;
use App\Models\UsuarioHorario;
use App\Services\GestorFestivos;
use App\Services\GestorFichajes;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class FestivosTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorFestivos $gestor;
    protected Usuario $marta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-fest-' . uniqid(),
            'nombre_comercial' => 'Salón Festivos',
            'email'            => 'fest@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->gestor = new GestorFestivos();

        $this->marta = Usuario::create([
            'nombre'    => 'Marta',
            'perfil_id' => Perfil::where('clave', 'profesional')->value('id'),
            'pin'       => '1234',
        ]);

        // Martes a sábado, de 9 a 17: ocho horas diarias
        foreach ([2, 3, 4, 5, 6] as $dia) {
            UsuarioHorario::create([
                'usuario_id' => $this->marta->id,
                'dia_semana' => $dia,
                'hora_ini'   => '09:00',
                'hora_fin'   => '17:00',
            ]);
        }
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
    // Festivos
    // ------------------------------------------------------------------

    public function test_crear_un_festivo_bloquea_la_agenda_de_todo_el_salon(): void
    {
        $festivo = $this->gestor->crear('2026-12-25', 'Navidad', 'NACIONAL');

        $excepcion = UsuarioExcepcion::first();

        $this->assertNotNull($excepcion);
        $this->assertNull($excepcion->usuario_id,
            'Con usuario_id nulo afecta a todo el salón, incluidos los que se '
            . 'den de alta después');
        $this->assertSame('CIERRE', $excepcion->tipo);
    }

    public function test_borrar_el_festivo_libera_el_dia(): void
    {
        $festivo = $this->gestor->crear('2026-12-25', 'Navidad');

        $this->assertSame(1, UsuarioExcepcion::count());

        $this->gestor->borrar($festivo);

        $this->assertSame(0, UsuarioExcepcion::count());
        $this->assertSame(0, Festivo::count());
    }

    public function test_no_se_repite_un_festivo_el_mismo_dia(): void
    {
        $this->gestor->crear('2026-12-25', 'Navidad');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ya hay un festivo');

        $this->gestor->crear('2026-12-25', 'Otra cosa');
    }

    public function test_media_jornada_no_bloquea_el_dia_entero(): void
    {
        $festivo = $this->gestor->crear('2026-12-24', 'Nochebuena', 'LOCAL', 'MANANA');

        $this->assertSame(0, UsuarioExcepcion::count(),
            'Si abre por la mañana, cerrar el día entero sería peor que no hacer nada');
        $this->assertFalse($festivo->cierraTodoElDia());
    }

    // ------------------------------------------------------------------
    // Importación
    // ------------------------------------------------------------------

    public function test_se_importan_los_festivos_de_un_ano(): void
    {
        $resultado = $this->gestor->importarAno(2026);

        // Nueve nacionales fijos + Día de Canarias + Jueves y Viernes Santo
        $this->assertSame(12, $resultado['creados']);
        $this->assertSame(12, Festivo::count());
    }

    public function test_importar_dos_veces_no_duplica(): void
    {
        $this->gestor->importarAno(2026);
        $segunda = $this->gestor->importarAno(2026);

        $this->assertSame(0, $segunda['creados']);
        $this->assertSame(12, $segunda['existentes']);
        $this->assertSame(12, Festivo::count());
    }

    public function test_se_puede_importar_sin_los_de_canarias(): void
    {
        $this->gestor->importarAno(2026, incluirCanarias: false);

        $this->assertNull(Festivo::whereDate('fecha', '2026-05-30')->first());
    }

    public function test_la_semana_santa_se_calcula_sola(): void
    {
        // Domingo de Pascua de 2026: 5 de abril
        $semana = $this->gestor->semanaSanta(2026);

        $this->assertArrayHasKey('2026-04-02', $semana);   // Jueves Santo
        $this->assertArrayHasKey('2026-04-03', $semana);   // Viernes Santo
        $this->assertSame('Jueves Santo', $semana['2026-04-02']);
    }

    public function test_la_semana_santa_cambia_cada_ano(): void
    {
        $this->assertNotSame(
            array_keys($this->gestor->semanaSanta(2026)),
            array_keys($this->gestor->semanaSanta(2027)),
        );
    }

    public function test_se_cuentan_los_festivos_que_caen_en_dia_de_apertura(): void
    {
        // Un lunes y un miércoles
        $this->gestor->crear('2026-01-05', 'Lunes cualquiera', 'LOCAL');   // lunes
        $this->gestor->crear('2026-01-07', 'Miércoles', 'LOCAL');          // miércoles

        // El salón abre de martes a sábado
        $this->assertSame(1, $this->gestor->festivosLaborables(2026),
            'Un festivo el día que ya cierras no te quita facturación');
    }

    // ------------------------------------------------------------------
    // Efecto en el registro de jornada
    // ------------------------------------------------------------------

    public function test_un_dia_normal_tiene_jornada_prevista(): void
    {
        $miercoles = Carbon::create(2026, 6, 10);   // miércoles

        $this->assertSame(480,
            (new GestorFichajes())->minutosPrevistos($this->marta, $miercoles));
    }

    public function test_un_festivo_no_tiene_jornada_prevista(): void
    {
        $miercoles = Carbon::create(2026, 6, 10);

        $this->gestor->crear($miercoles, 'Fiestas del pueblo', 'LOCAL');

        $this->assertSame(0,
            (new GestorFichajes())->minutosPrevistos($this->marta, $miercoles),
            'Exigir ocho horas un día festivo daría una desviación falsa');
    }

    public function test_un_dia_de_cierre_semanal_no_tiene_jornada_prevista(): void
    {
        $lunes = Carbon::create(2026, 6, 8);   // lunes, y el salón cierra

        $this->assertSame(0,
            (new GestorFichajes())->minutosPrevistos($this->marta, $lunes));
    }
}
