<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Fichaje;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Services\GestorFichajes;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class FichajesTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorFichajes $gestor;
    protected Usuario $marta;
    protected Usuario $jefa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-fich-' . uniqid(),
            'nombre_comercial' => 'Salón Fichajes',
            'email'            => 'fich@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->gestor = new GestorFichajes();

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

    protected function ficharA(string $tipo, string $hora, ?Carbon $dia = null): Fichaje
    {
        $dia ??= now();

        [$h, $m] = explode(':', $hora);

        return $this->gestor->fichar(
            $this->marta,
            $tipo,
            'TERMINAL',
            $dia->copy()->setTime((int) $h, (int) $m),
        );
    }

    // ------------------------------------------------------------------
    // Secuencia
    // ------------------------------------------------------------------

    public function test_al_principio_se_esta_fuera(): void
    {
        $this->assertSame('FUERA', $this->gestor->estado($this->marta));
    }

    public function test_fichar_entrada_pone_en_trabajando(): void
    {
        $this->ficharA('ENTRADA', '09:00');

        $this->assertSame('TRABAJANDO', $this->gestor->estado($this->marta));
    }

    public function test_no_se_puede_entrar_dos_veces(): void
    {
        $this->ficharA('ENTRADA', '09:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ya estás dentro');

        $this->ficharA('ENTRADA', '09:30');
    }

    public function test_no_se_puede_salir_sin_haber_entrado(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Todavía no has fichado la entrada');

        $this->ficharA('SALIDA', '18:00');
    }

    public function test_la_pausa_cambia_el_estado(): void
    {
        $this->ficharA('ENTRADA', '09:00');
        $this->ficharA('PAUSA_INICIO', '14:00');

        $this->assertSame('PAUSA', $this->gestor->estado($this->marta));

        $this->ficharA('PAUSA_FIN', '15:00');

        $this->assertSame('TRABAJANDO', $this->gestor->estado($this->marta));
    }

    public function test_no_se_puede_empezar_pausa_estando_en_pausa(): void
    {
        $this->ficharA('ENTRADA', '09:00');
        $this->ficharA('PAUSA_INICIO', '14:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Estás en pausa');

        $this->ficharA('PAUSA_INICIO', '14:30');
    }

    public function test_no_se_puede_fichar_antes_del_ultimo_fichaje(): void
    {
        $this->ficharA('ENTRADA', '09:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hay un fichaje posterior');

        $this->ficharA('SALIDA', '08:00');
    }

    public function test_el_boton_unico_ficha_lo_que_toca(): void
    {
        $this->assertSame('ENTRADA', $this->gestor->ficharSiguiente($this->marta)->tipo);
        $this->assertSame('SALIDA', $this->gestor->ficharSiguiente($this->marta)->tipo);
    }

    // ------------------------------------------------------------------
    // Cálculo de horas
    // ------------------------------------------------------------------

    public function test_una_jornada_simple_se_calcula_bien(): void
    {
        $this->ficharA('ENTRADA', '09:00');
        $this->ficharA('SALIDA', '17:00');

        $jornada = $this->gestor->jornada($this->marta, now());

        $this->assertSame(480, $jornada['minutos']);
        $this->assertFalse($jornada['incompleta']);
    }

    public function test_la_pausa_resta_del_tiempo_trabajado(): void
    {
        $this->ficharA('ENTRADA', '09:00');
        $this->ficharA('PAUSA_INICIO', '14:00');
        $this->ficharA('PAUSA_FIN', '15:00');
        $this->ficharA('SALIDA', '18:00');

        $jornada = $this->gestor->jornada($this->marta, now());

        // 9 horas menos 1 de pausa
        $this->assertSame(480, $jornada['minutos']);
        $this->assertSame(60, $jornada['pausa']);
    }

    public function test_una_jornada_partida_suma_los_dos_tramos(): void
    {
        $this->ficharA('ENTRADA', '09:00');
        $this->ficharA('SALIDA', '13:00');
        $this->ficharA('ENTRADA', '16:00');
        $this->ficharA('SALIDA', '20:00');

        $this->assertSame(480, $this->gestor->jornada($this->marta, now())['minutos']);
    }

    public function test_una_entrada_sin_salida_marca_la_jornada_incompleta(): void
    {
        $this->ficharA('ENTRADA', '09:00');

        $jornada = $this->gestor->jornada($this->marta, now());

        $this->assertTrue($jornada['incompleta']);
        $this->assertSame(0, $jornada['minutos'],
            'Inventar una hora de salida sería falsear el registro');
    }

    public function test_el_resumen_mensual_suma_los_dias(): void
    {
        $lunes = now()->startOfMonth()->next(Carbon::MONDAY);

        foreach ([0, 1, 2] as $dias) {
            $dia = $lunes->copy()->addDays($dias);

            $this->ficharA('ENTRADA', '09:00', $dia);
            $this->ficharA('SALIDA', '17:00', $dia);
        }

        $resumen = $this->gestor->mes($this->marta, $lunes->year, $lunes->month);

        $this->assertSame(1440, $resumen['total_minutos']);   // 3 × 8 h
        $this->assertSame(3, $resumen['dias_trabajados']);
        $this->assertSame(480, $resumen['media_diaria']);
    }

    // ------------------------------------------------------------------
    // Inmutabilidad
    // ------------------------------------------------------------------

    public function test_no_se_puede_cambiar_la_hora_de_un_fichaje(): void
    {
        $fichaje = $this->ficharA('ENTRADA', '09:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede modificar');

        $fichaje->update(['fecha_hora' => now()->setTime(8, 0)]);
    }

    public function test_no_se_puede_borrar_un_fichaje(): void
    {
        $fichaje = $this->ficharA('ENTRADA', '09:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cuatro años');

        $fichaje->delete();
    }

    // ------------------------------------------------------------------
    // Correcciones
    // ------------------------------------------------------------------

    public function test_corregir_crea_un_fichaje_nuevo_y_anula_el_viejo(): void
    {
        $original = $this->ficharA('ENTRADA', '09:00');

        $corregido = $this->gestor->corregir(
            $original,
            now()->setTime(8, 30),
            'Fichó tarde por avería del terminal',
            $this->jefa,
        );

        $this->assertTrue($original->fresh()->anulado);
        $this->assertSame($original->id, $corregido->corrige_a_id);
        $this->assertSame('08:30', $corregido->hora());
        $this->assertSame('MANUAL', $corregido->origen);
    }

    public function test_el_original_se_conserva_para_la_inspeccion(): void
    {
        $original = $this->ficharA('ENTRADA', '09:00');

        $this->gestor->corregir($original, now()->setTime(8, 30), 'Motivo', $this->jefa);

        $this->assertSame(2, Fichaje::count(),
            'Los dos registros tienen que existir: el original y la corrección');

        $enBase = Fichaje::find($original->id);

        $this->assertNotNull($enBase);
        $this->assertSame('09:00', $enBase->hora());
        $this->assertSame($this->jefa->id, $enBase->anulado_por);
        $this->assertSame('Motivo', $enBase->motivo_correccion);
    }

    public function test_el_fichaje_anulado_no_cuenta_en_las_horas(): void
    {
        $entrada = $this->ficharA('ENTRADA', '09:00');
        $this->ficharA('SALIDA', '17:00');

        $this->gestor->corregir($entrada, now()->setTime(8, 0), 'Corrección', $this->jefa);

        // 8:00 a 17:00 son 9 horas, no 8
        $this->assertSame(540, $this->gestor->jornada($this->marta, now())['minutos']);
    }

    public function test_no_se_corrige_dos_veces_el_mismo_fichaje(): void
    {
        $original = $this->ficharA('ENTRADA', '09:00');

        $this->gestor->corregir($original, now()->setTime(8, 30), 'Primera', $this->jefa);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ya fue corregido');

        $this->gestor->corregir($original->fresh(), now()->setTime(8, 0), 'Segunda', $this->jefa);
    }

    public function test_una_correccion_sin_motivo_se_rechaza(): void
    {
        $original = $this->ficharA('ENTRADA', '09:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('motivo');

        $this->gestor->corregir($original, now()->setTime(8, 30), '   ', $this->jefa);
    }

    public function test_un_fichaje_manual_queda_marcado_como_tal(): void
    {
        $fichaje = $this->gestor->anadirManual(
            $this->marta,
            'SALIDA',
            now()->setTime(18, 0),
            'Olvidó fichar al salir',
            $this->jefa,
        );

        $this->assertTrue($fichaje->esManual());
        $this->assertSame($this->jefa->id, $fichaje->registrado_por);
        $this->assertSame('Olvidó fichar al salir', $fichaje->motivo_correccion);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    public function test_las_horas_se_formatean_para_leerlas(): void
    {
        $this->assertSame('8 h 00 min', GestorFichajes::horasYMinutos(480));
        $this->assertSame('7 h 30 min', GestorFichajes::horasYMinutos(450));
        $this->assertSame('0 h 45 min', GestorFichajes::horasYMinutos(45));
    }

    public function test_se_sabe_quien_esta_dentro(): void
    {
        $this->ficharA('ENTRADA', '09:00');

        $dentro = $this->gestor->quienEstaDentro();

        $this->assertCount(1, $dentro);
        $this->assertSame('Marta', $dentro->first()->nombre);
    }
}
