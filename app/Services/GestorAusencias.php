<?php

namespace App\Services;

use App\Models\Ausencia;
use App\Models\Auditoria;
use App\Models\Usuario;
use App\Models\UsuarioExcepcion;
use App\Support\SesionSalon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Vacaciones, bajas y permisos.
 *
 * El motor de huecos ya respeta `usuario_excepciones` desde la Fase 3.
 * Este servicio añade encima el flujo que faltaba —solicitar, aprobar,
 * rechazar— y, al aprobar, crea la excepción correspondiente. Así la
 * agenda deja de ofrecer huecos sin tocar el motor.
 */
class GestorAusencias
{
    // ------------------------------------------------------------------
    // Solicitar
    // ------------------------------------------------------------------

    public function solicitar(
        Usuario $usuario,
        string $tipo,
        Carbon|string $desde,
        Carbon|string $hasta,
        ?string $motivo = null,
        ?string $medioDia = null,
    ): Ausencia {
        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->startOfDay();

        if ($hasta->lt($desde)) {
            throw new RuntimeException('La fecha de fin es anterior a la de inicio.');
        }

        if ($medioDia && ! $desde->isSameDay($hasta)) {
            throw new RuntimeException('Un medio día solo puede ser de una única jornada.');
        }

        $this->comprobarSolapamiento($usuario, $desde, $hasta);

        $dias = $this->calcularDias($usuario, $tipo, $desde, $hasta, $medioDia);

        /**
         * Se avisa si no le quedan días, pero NO se bloquea.
         *
         * Puede haber acuerdos particulares, días del año anterior o
         * permisos sin sueldo. Quien aprueba decide con la información
         * delante; el software no debería impedirlo.
         */
        $ausencia = Ausencia::create([
            'usuario_id'      => $usuario->id,
            'tipo'            => strtoupper($tipo),
            'desde'           => $desde->toDateString(),
            'hasta'           => $hasta->toDateString(),
            'medio_dia'       => $medioDia,
            'estado'          => 'SOLICITADA',
            'motivo'          => $motivo,
            'dias_computados' => $dias,
            'solicitada_por'  => SesionSalon::usuario()?->id ?? $usuario->id,
        ]);

        Auditoria::registrar('ausencia_solicitada', 'ausencias', $ausencia->id, [
            'usuario' => $usuario->nombre,
            'tipo'    => $tipo,
            'fechas'  => $ausencia->resumenFechas(),
        ]);

        return $ausencia;
    }

    /**
     * Alta directa, sin pasar por aprobación.
     * Lo usa el responsable cuando registra una baja médica.
     */
    public function registrarAprobada(
        Usuario $usuario,
        string $tipo,
        Carbon|string $desde,
        Carbon|string $hasta,
        ?string $motivo = null,
    ): Ausencia {
        $ausencia = $this->solicitar($usuario, $tipo, $desde, $hasta, $motivo);

        return $this->aprobar($ausencia, SesionSalon::usuario());
    }

    protected function comprobarSolapamiento(Usuario $usuario, Carbon $desde, Carbon $hasta): void
    {
        $existe = Ausencia::where('usuario_id', $usuario->id)
            ->whereIn('estado', ['SOLICITADA', 'APROBADA'])
            ->where('desde', '<=', $hasta->toDateString())
            ->where('hasta', '>=', $desde->toDateString())
            ->first();

        if ($existe) {
            throw new RuntimeException(
                'Ya hay una ausencia en esas fechas: '
                . $existe->etiqueta() . ' del ' . $existe->resumenFechas() . '.'
            );
        }
    }

    /**
     * Días que consume la ausencia.
     *
     * Se cuentan solo los días en que la persona tenía horario: si el
     * salón cierra los lunes, unas vacaciones de lunes a domingo son
     * seis días, no siete. Contar los siete sería robarle un día.
     */
    public function calcularDias(
        Usuario $usuario,
        string $tipo,
        Carbon $desde,
        Carbon $hasta,
        ?string $medioDia = null,
    ): float {
        if (! in_array(strtoupper($tipo), Ausencia::CONSUMEN_CUPO, true)) {
            return 0;
        }

        if ($medioDia) {
            return 0.5;
        }

        $diasConHorario = $usuario->horarios()->pluck('dia_semana')->unique();

        // Sin horario configurado, se cuentan los laborables de lunes a sábado
        if ($diasConHorario->isEmpty()) {
            $diasConHorario = collect([1, 2, 3, 4, 5, 6]);
        }

        $dias = 0;
        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            if ($diasConHorario->contains((int) $fecha->dayOfWeek)) {
                $dias++;
            }

            $fecha->addDay();
        }

        return (float) $dias;
    }

    // ------------------------------------------------------------------
    // Resolver
    // ------------------------------------------------------------------

    public function aprobar(Ausencia $ausencia, Usuario $responsable, ?string $respuesta = null): Ausencia
    {
        if ($ausencia->estado === 'APROBADA') {
            return $ausencia;
        }

        if ($ausencia->estado === 'CANCELADA') {
            throw new RuntimeException('Esa ausencia está cancelada.');
        }

        return DB::transaction(function () use ($ausencia, $responsable, $respuesta) {
            /**
             * Se crea la excepción de horario.
             *
             * Es lo que hace que la agenda deje de ofrecer huecos: el
             * motor de huecos ya la consulta desde la Fase 3, así que no
             * hay que tocarlo.
             */
            $excepcion = UsuarioExcepcion::create([
                'usuario_id' => $ausencia->usuario_id,
                'tipo'       => $this->tipoExcepcion($ausencia->tipo),
                'desde'      => $ausencia->desde->toDateString(),
                'hasta'      => $ausencia->hasta->toDateString(),
                'motivo'     => $ausencia->etiqueta()
                                . ($ausencia->motivo ? ': ' . $ausencia->motivo : ''),
            ]);

            $ausencia->update([
                'estado'       => 'APROBADA',
                'respuesta'    => $respuesta,
                'resuelta_por' => $responsable->id,
                'resuelta_en'  => now(),
                'excepcion_id' => $excepcion->id,
            ]);

            Auditoria::registrar('ausencia_aprobada', 'ausencias', $ausencia->id, [
                'usuario' => $ausencia->usuario?->nombre,
                'fechas'  => $ausencia->resumenFechas(),
            ], $responsable->id);

            return $ausencia->fresh();
        });
    }

    public function rechazar(Ausencia $ausencia, Usuario $responsable, string $respuesta): Ausencia
    {
        if (blank(trim($respuesta))) {
            throw new RuntimeException(
                'Hay que explicar por qué se rechaza. Un «no» sin motivo '
                . 'genera más conversaciones de las que ahorra.'
            );
        }

        $ausencia->update([
            'estado'       => 'RECHAZADA',
            'respuesta'    => $respuesta,
            'resuelta_por' => $responsable->id,
            'resuelta_en'  => now(),
        ]);

        Auditoria::registrar('ausencia_rechazada', 'ausencias', $ausencia->id, [
            'usuario'   => $ausencia->usuario?->nombre,
            'respuesta' => $respuesta,
        ], $responsable->id);

        return $ausencia->fresh();
    }

    /** Cancela y retira la excepción para liberar la agenda. */
    public function cancelar(Ausencia $ausencia, Usuario $responsable, ?string $motivo = null): Ausencia
    {
        return DB::transaction(function () use ($ausencia, $responsable, $motivo) {
            if ($ausencia->excepcion) {
                $ausencia->excepcion->delete();
            }

            $ausencia->update([
                'estado'       => 'CANCELADA',
                'respuesta'    => $motivo,
                'resuelta_por' => $responsable->id,
                'resuelta_en'  => now(),
                'excepcion_id' => null,
            ]);

            Auditoria::registrar('ausencia_cancelada', 'ausencias', $ausencia->id, [
                'usuario' => $ausencia->usuario?->nombre,
                'motivo'  => $motivo,
            ], $responsable->id);

            return $ausencia->fresh();
        });
    }

    protected function tipoExcepcion(string $tipo): string
    {
        return match ($tipo) {
            'VACACIONES' => 'VACACIONES',
            'BAJA', 'MATERNIDAD' => 'BAJA',
            default => 'AUSENCIA',
        };
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** ¿Está ausente este día? Lo usa el registro de jornada. */
    public function estaAusente(Usuario $usuario, Carbon|string $fecha): ?Ausencia
    {
        return Ausencia::aprobadas()
            ->where('usuario_id', $usuario->id)
            ->enFecha($fecha)
            ->first();
    }

    /** Cupo de vacaciones: cuánto tiene, cuánto ha gastado y cuánto queda. */
    public function cupo(Usuario $usuario, ?int $ano = null): array
    {
        $ano ??= now()->year;

        $total = (float) ($usuario->dias_vacaciones ?? 22);

        $gastados = (float) Ausencia::aprobadas()
            ->where('usuario_id', $usuario->id)
            ->delAno($ano)
            ->sum('dias_computados');

        $solicitados = (float) Ausencia::pendientes()
            ->where('usuario_id', $usuario->id)
            ->delAno($ano)
            ->sum('dias_computados');

        return [
            'total'       => $total,
            'gastados'    => $gastados,
            'solicitados' => $solicitados,
            'restantes'   => round($total - $gastados, 1),

            // Lo que quedaría si se aprobara todo lo pendiente
            'proyectado'  => round($total - $gastados - $solicitados, 1),
            'ano'         => $ano,
        ];
    }

    /** Quién está ausente hoy. Para el inicio del panel. */
    public function ausentesHoy(): Collection
    {
        return Ausencia::aprobadas()
            ->enFecha(now())
            ->with('usuario')
            ->get();
    }

    /**
     * Calendario del equipo para un mes.
     * Sirve para ver de un vistazo si coinciden dos personas.
     */
    public function calendario(int $ano, int $mes): array
    {
        $desde = Carbon::create($ano, $mes, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $ausencias = Ausencia::aprobadas()
            ->where('desde', '<=', $hasta->toDateString())
            ->where('hasta', '>=', $desde->toDateString())
            ->with('usuario')
            ->get();

        $usuarios = Usuario::activos()->orderBy('nombre')->get();

        $filas = [];

        foreach ($usuarios as $usuario) {
            $dias = [];
            $fecha = $desde->copy();

            while ($fecha->lte($hasta)) {
                $ausencia = $ausencias->first(
                    fn (Ausencia $a) => $a->usuario_id === $usuario->id && $a->cubre($fecha)
                );

                $dias[] = [
                    'fecha'    => $fecha->copy(),
                    'ausencia' => $ausencia,
                    'finde'    => in_array((int) $fecha->dayOfWeek, [0, 6], true),
                ];

                $fecha->addDay();
            }

            $filas[] = ['usuario' => $usuario, 'dias' => $dias];
        }

        return [
            'filas' => $filas,
            'desde' => $desde,
            'hasta' => $hasta,

            /**
             * Días en que coinciden dos o más ausencias.
             * Es lo que hay que mirar antes de aprobar: dejar el salón
             * sin nadie un sábado es el error caro.
             */
            'solapes' => $this->diasConSolape($filas),
        ];
    }

    protected function diasConSolape(array $filas): array
    {
        $conteo = [];

        foreach ($filas as $fila) {
            foreach ($fila['dias'] as $dia) {
                if ($dia['ausencia']) {
                    $clave = $dia['fecha']->toDateString();
                    $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
                }
            }
        }

        return array_keys(array_filter($conteo, fn ($n) => $n > 1));
    }
}
