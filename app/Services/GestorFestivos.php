<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Festivo;
use App\Models\UsuarioExcepcion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Festivos y cierres del salón.
 *
 * Se apoya en `usuario_excepciones` con `usuario_id = NULL`, que el motor
 * de huecos ya interpreta como «afecta a todo el salón» desde la Fase 3.
 */
class GestorFestivos
{
    /**
     * Festivos nacionales de España.
     *
     * Solo los de fecha fija. Los móviles —Jueves Santo, Viernes Santo,
     * Corpus— dependen de la Pascua y se calculan aparte.
     *
     * OJO: esto es una AYUDA para no teclear catorce fechas a mano, no
     * una fuente oficial. Cada comunidad y cada municipio publican su
     * calendario en el BOE y en el boletín autonómico, y hay años en que
     * un festivo nacional se traslada. Hay que revisarlo.
     */
    public const NACIONALES_FIJOS = [
        '01-01' => 'Año Nuevo',
        '01-06' => 'Epifanía del Señor',
        '05-01' => 'Fiesta del Trabajo',
        '08-15' => 'Asunción de la Virgen',
        '10-12' => 'Fiesta Nacional de España',
        '11-01' => 'Todos los Santos',
        '12-06' => 'Día de la Constitución',
        '12-08' => 'Inmaculada Concepción',
        '12-25' => 'Natividad del Señor',
    ];

    /** Canarias. */
    public const CANARIAS = [
        '05-30' => 'Día de Canarias',
    ];

    // ------------------------------------------------------------------

    public function crear(
        Carbon|string $fecha,
        string $nombre,
        string $ambito = 'LOCAL',
        ?string $mediaJornada = null,
        ?string $observaciones = null,
    ): Festivo {
        $fecha = Carbon::parse($fecha)->startOfDay();

        if (Festivo::whereDate('fecha', $fecha)->exists()) {
            throw new RuntimeException(
                'Ya hay un festivo el ' . $fecha->format('d/m/Y') . '.'
            );
        }

        return DB::transaction(function () use ($fecha, $nombre, $ambito, $mediaJornada, $observaciones) {
            $festivo = Festivo::create([
                'fecha'         => $fecha->toDateString(),
                'nombre'        => $nombre,
                'ambito'        => strtoupper($ambito),
                'media_jornada' => $mediaJornada,
                'observaciones' => $observaciones,
            ]);

            $this->sincronizarExcepcion($festivo);

            Auditoria::registrar('festivo_creado', 'festivos', $festivo->id, [
                'fecha'  => $fecha->format('d/m/Y'),
                'nombre' => $nombre,
            ]);

            return $festivo->fresh();
        });
    }

    /**
     * Crea o actualiza la excepción que bloquea la agenda.
     *
     * Con `usuario_id` a null afecta a todo el salón, incluidos los
     * profesionales que se den de alta después. Crear una excepción por
     * persona obligaría a acordarse de añadirla en cada alta.
     */
    protected function sincronizarExcepcion(Festivo $festivo): void
    {
        if ($festivo->excepcion) {
            $festivo->excepcion->delete();
        }

        // Media jornada no bloquea el día: se gestiona como horario
        // especial, y eso todavía no lo cubrimos. Se avisa en pantalla.
        if (! $festivo->cierraTodoElDia()) {
            $festivo->forceFill(['excepcion_id' => null])->save();

            return;
        }

        $excepcion = UsuarioExcepcion::create([
            'usuario_id' => null,
            'tipo'       => 'CIERRE',
            'desde'      => $festivo->fecha->toDateString(),
            'hasta'      => $festivo->fecha->toDateString(),
            'motivo'     => $festivo->nombre,
        ]);

        $festivo->forceFill(['excepcion_id' => $excepcion->id])->save();
    }

    public function actualizar(Festivo $festivo, array $datos): Festivo
    {
        return DB::transaction(function () use ($festivo, $datos) {
            $festivo->update($datos);

            $this->sincronizarExcepcion($festivo->fresh());

            return $festivo->fresh();
        });
    }

    public function borrar(Festivo $festivo): void
    {
        DB::transaction(function () use ($festivo) {
            if ($festivo->excepcion) {
                $festivo->excepcion->delete();
            }

            Auditoria::registrar('festivo_borrado', 'festivos', $festivo->id, [
                'fecha'  => $festivo->fecha->format('d/m/Y'),
                'nombre' => $festivo->nombre,
            ]);

            $festivo->delete();
        });
    }

    // ------------------------------------------------------------------
    // Importación
    // ------------------------------------------------------------------

    /**
     * Da de alta los festivos nacionales de un año, más los de Canarias
     * si procede. Los que ya existan se dejan como están.
     *
     * @return array{creados: int, existentes: int}
     */
    public function importarAno(int $ano, bool $incluirCanarias = true): array
    {
        $creados = 0;
        $existentes = 0;

        $lista = self::NACIONALES_FIJOS;

        if ($incluirCanarias) {
            $lista = array_merge($lista, self::CANARIAS);
        }

        foreach ($lista as $diaMes => $nombre) {
            [$mes, $dia] = explode('-', $diaMes);

            $fecha = Carbon::create($ano, (int) $mes, (int) $dia);

            if (Festivo::whereDate('fecha', $fecha)->exists()) {
                $existentes++;

                continue;
            }

            $this->crear(
                $fecha,
                $nombre,
                isset(self::CANARIAS[$diaMes]) ? 'AUTONOMICO' : 'NACIONAL',
            );

            $creados++;
        }

        // Los móviles de Semana Santa
        foreach ($this->semanaSanta($ano) as $fecha => $nombre) {
            if (Festivo::whereDate('fecha', $fecha)->exists()) {
                $existentes++;

                continue;
            }

            $this->crear($fecha, $nombre, 'NACIONAL');
            $creados++;
        }

        return ['creados' => $creados, 'existentes' => $existentes];
    }

    /**
     * Jueves y Viernes Santo.
     *
     * PHP calcula la Pascua con easter_date(), pero esa función necesita
     * la extensión calendar, que no siempre está. Se usa el algoritmo de
     * Gauss, que son cuatro líneas y no depende de nada.
     */
    public function semanaSanta(int $ano): array
    {
        $pascua = $this->domingoDePascua($ano);

        return [
            $pascua->copy()->subDays(3)->toDateString() => 'Jueves Santo',
            $pascua->copy()->subDays(2)->toDateString() => 'Viernes Santo',
        ];
    }

    protected function domingoDePascua(int $ano): Carbon
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($ano, $mes, $dia);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public function esFestivo(Carbon|string $fecha): ?Festivo
    {
        return Festivo::whereDate('fecha', Carbon::parse($fecha))->first();
    }

    /** Festivos de un año, ordenados. */
    public function delAno(int $ano)
    {
        return Festivo::delAno($ano)->orderBy('fecha')->get();
    }

    /**
     * Cuántos festivos caen en día que el salón abre.
     *
     * Un festivo en lunes, si el salón cierra los lunes, no quita
     * facturación: conviene saberlo antes de planificar el año.
     */
    public function festivosLaborables(int $ano, array $diasQueAbre = [2, 3, 4, 5, 6]): int
    {
        return $this->delAno($ano)
            ->filter(fn (Festivo $f) => in_array((int) $f->fecha->dayOfWeek, $diasQueAbre, true))
            ->count();
    }
}
