<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Fichaje;
use App\Models\Usuario;
use App\Support\SesionSalon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Registro de jornada.
 *
 * Obligatorio desde 2019 y con un reglamento en camino que exige
 * trazabilidad e inmutabilidad. Aquí está toda la lógica de qué se puede
 * fichar en cada momento y cómo se cuentan las horas.
 */
class GestorFichajes
{
    /** Cuatro años, que es lo que exige el Estatuto de los Trabajadores. */
    public const ANOS_CONSERVACION = 4;

    // ------------------------------------------------------------------
    // Fichar
    // ------------------------------------------------------------------

    /**
     * Registra un fichaje comprobando que tiene sentido.
     *
     * No se puede entrar dos veces seguidas ni salir sin haber entrado:
     * un registro con incoherencias no sirve como prueba y da más trabajo
     * de corregir que de hacer bien.
     */
    public function fichar(
        Usuario $usuario,
        string $tipo,
        string $origen = 'TERMINAL',
        ?Carbon $momento = null,
        ?string $observaciones = null,
    ): Fichaje {
        $tipo = strtoupper($tipo);
        $momento ??= now();

        if (! array_key_exists($tipo, Fichaje::TIPOS)) {
            throw new RuntimeException("Tipo de fichaje desconocido: {$tipo}.");
        }

        $this->comprobarSecuencia($usuario, $tipo, $momento);

        $peticion = request();

        $fichaje = Fichaje::create([
            'usuario_id'    => $usuario->id,
            'fecha'         => $momento->toDateString(),
            'fecha_hora'    => $momento,
            'tipo'          => $tipo,
            'origen'        => $origen,
            'terminal_id'   => SesionSalon::terminal()?->id,
            'ip'            => $peticion?->ip(),
            'dispositivo'   => mb_substr((string) $peticion?->userAgent(), 0, 200),
            'observaciones' => $observaciones,
            'registrado_por'=> SesionSalon::usuario()?->id ?? $usuario->id,
        ]);

        Auditoria::registrar('fichaje', 'fichajes', $fichaje->id, [
            'usuario' => $usuario->nombre,
            'tipo'    => $tipo,
            'hora'    => $momento->format('d/m/Y H:i'),
            'origen'  => $origen,
        ]);

        return $fichaje;
    }

    /**
     * Ficha lo que toque según el estado actual.
     * Es lo que hace el botón único del panel.
     */
    public function ficharSiguiente(Usuario $usuario, string $origen = 'PANEL'): Fichaje
    {
        $siguiente = match ($this->estado($usuario)) {
            'FUERA'     => 'ENTRADA',
            'TRABAJANDO'=> 'SALIDA',
            'PAUSA'     => 'PAUSA_FIN',
            default     => 'ENTRADA',
        };

        return $this->fichar($usuario, $siguiente, $origen);
    }

    protected function comprobarSecuencia(Usuario $usuario, string $tipo, Carbon $momento): void
    {
        $ultimo = $this->ultimoFichaje($usuario);

        // No se puede fichar en el pasado por delante de otro fichaje
        if ($ultimo && $momento->lt($ultimo->fecha_hora)) {
            throw new RuntimeException(
                'Hay un fichaje posterior a esa hora ('
                . $ultimo->fecha_hora->format('d/m/Y H:i') . '). '
                . 'Para arreglarlo, corrige ese fichaje.'
            );
        }

        $estado = $this->estado($usuario);

        $permitido = match ($estado) {
            'FUERA'      => ['ENTRADA'],
            'TRABAJANDO' => ['SALIDA', 'PAUSA_INICIO'],
            'PAUSA'      => ['PAUSA_FIN', 'SALIDA'],
            default      => ['ENTRADA'],
        };

        if (! in_array($tipo, $permitido, true)) {
            throw new RuntimeException(match ($estado) {
                'FUERA'      => 'Todavía no has fichado la entrada.',
                'TRABAJANDO' => 'Ya estás dentro: solo puedes salir o empezar una pausa.',
                'PAUSA'      => 'Estás en pausa: primero termina la pausa.',
                default      => 'Ese fichaje no encaja con tu estado actual.',
            });
        }
    }

    // ------------------------------------------------------------------
    // Estado
    // ------------------------------------------------------------------

    public function ultimoFichaje(Usuario $usuario): ?Fichaje
    {
        return Fichaje::validos()
            ->deUsuario($usuario->id)
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * FUERA, TRABAJANDO o PAUSA.
     *
     * Se mira el último fichaje sin filtrar por día: quien entra a las
     * 22:00 y sale a las 02:00 sigue trabajando pasada la medianoche.
     */
    public function estado(Usuario $usuario): string
    {
        $ultimo = $this->ultimoFichaje($usuario);

        if (! $ultimo) {
            return 'FUERA';
        }

        return match ($ultimo->tipo) {
            'ENTRADA', 'PAUSA_FIN' => 'TRABAJANDO',
            'PAUSA_INICIO'         => 'PAUSA',
            default                => 'FUERA',
        };
    }

    // ------------------------------------------------------------------
    // Cálculo de horas
    // ------------------------------------------------------------------

    /**
     * Minutos trabajados a partir de una lista de fichajes.
     *
     * Se recorren en orden emparejando entradas con salidas. Las pausas
     * restan. Un fichaje sin pareja —olvidó fichar la salida— no suma
     * nada y se marca aparte: inventar una hora de salida sería falsear
     * el registro.
     *
     * @return array{minutos: int, pausa: int, incompleta: bool}
     */
    public function calcular(Collection $fichajes): array
    {
        $fichajes = $fichajes->sortBy('fecha_hora')->values();

        $minutos = 0;
        $pausa = 0;
        $entrada = null;
        $inicioPausa = null;
        $incompleta = false;

        foreach ($fichajes as $fichaje) {
            match ($fichaje->tipo) {
                'ENTRADA' => $entrada = $fichaje->fecha_hora,

                'SALIDA' => (function () use (&$minutos, &$entrada, $fichaje, &$incompleta) {
                    if ($entrada) {
                        $minutos += (int) $entrada->diffInMinutes($fichaje->fecha_hora);
                        $entrada = null;
                    } else {
                        $incompleta = true;
                    }
                })(),

                'PAUSA_INICIO' => $inicioPausa = $fichaje->fecha_hora,

                'PAUSA_FIN' => (function () use (&$pausa, &$inicioPausa, $fichaje) {
                    if ($inicioPausa) {
                        $pausa += (int) $inicioPausa->diffInMinutes($fichaje->fecha_hora);
                        $inicioPausa = null;
                    }
                })(),

                default => null,
            };
        }

        // Entrada sin salida: la jornada está abierta o falta un fichaje
        if ($entrada || $inicioPausa) {
            $incompleta = true;
        }

        return [
            'minutos'    => max(0, $minutos - $pausa),
            'pausa'      => $pausa,
            'incompleta' => $incompleta,
        ];
    }

    /** Jornada de un día concreto. */
    public function jornada(Usuario $usuario, Carbon|string $fecha): array
    {
        $fichajes = Fichaje::validos()
            ->deUsuario($usuario->id)
            ->delDia($fecha)
            ->orderBy('fecha_hora')
            ->get();

        $calculo = $this->calcular($fichajes);
        $ausencia = (new GestorAusencias())->estaAusente($usuario, $fecha);

        return array_merge(
            [
                'fichajes' => $fichajes,
                'fecha'    => Carbon::parse($fecha),
                'ausencia' => $ausencia,
            ],
            $this->conAusencia($calculo, $ausencia, $fichajes->isEmpty()),
        );
    }

    /**
     * Un día de vacaciones no es una incidencia.
     *
     * Sin esto, cada persona vuelve de sus vacaciones con quince días
     * marcados en rojo en su registro, y quien lo revisa acaba ignorando
     * los avisos. Cuando un aviso salta siempre, deja de avisar.
     */
    protected function conAusencia(array $calculo, ?\App\Models\Ausencia $ausencia, bool $sinFichajes): array
    {
        if ($ausencia && $sinFichajes) {
            $calculo['incompleta'] = false;
        }

        return $calculo;
    }

    /**
     * Minutos que esa persona tenía previsto trabajar ese día.
     *
     * Sale del horario configurado en la Fase 3. Comparar lo previsto con
     * lo fichado es lo que convierte el registro en información útil:
     * sin esa referencia, «8 h 15 min» no dice si hubo horas de más.
     */
    public function minutosPrevistos(Usuario $usuario, Carbon|string $fecha): int
    {
        $fecha = Carbon::parse($fecha);

        // Un festivo o una ausencia no tienen jornada prevista
        if ((new GestorFestivos())->esFestivo($fecha)) {
            return 0;
        }

        if ((new GestorAusencias())->estaAusente($usuario, $fecha)) {
            return 0;
        }

        return (int) \App\Models\UsuarioHorario::where('usuario_id', $usuario->id)
            ->where('dia_semana', (int) $fecha->dayOfWeek)
            ->get()
            ->sum(function ($horario) {
                return Carbon::parse($horario->hora_ini)
                    ->diffInMinutes(Carbon::parse($horario->hora_fin));
            });
    }

    /** Resumen de un mes, día a día. */
    public function mes(Usuario $usuario, int $ano, int $mes): array
    {
        $desde = Carbon::create($ano, $mes, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $fichajes = Fichaje::validos()
            ->deUsuario($usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha_hora')
            ->get()
            ->groupBy(fn (Fichaje $f) => $f->fecha->toDateString());

        // Las ausencias del mes, de una sola consulta
        $ausencias = \App\Models\Ausencia::aprobadas()
            ->where('usuario_id', $usuario->id)
            ->where('desde', '<=', $hasta->toDateString())
            ->where('hasta', '>=', $desde->toDateString())
            ->get();

        $dias = [];
        $totalMinutos = 0;
        $totalPrevisto = 0;
        $totalExtra = 0;
        $diasIncompletos = 0;
        $diasAusencia = 0;

        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            $clave = $fecha->toDateString();
            $delDia = $fichajes->get($clave, collect());

            $ausencia = $ausencias->first(fn ($a) => $a->cubre($fecha));

            $calculo = $this->conAusencia(
                $this->calcular($delDia),
                $ausencia,
                $delDia->isEmpty(),
            );

            $previstos = $this->minutosPrevistos($usuario, $fecha);

            $dias[] = [
                'fecha'      => $fecha->copy(),
                'fichajes'   => $delDia,
                'minutos'    => $calculo['minutos'],
                'pausa'      => $calculo['pausa'],
                'incompleta' => $calculo['incompleta'],
                'trabajado'  => $delDia->isNotEmpty(),
                'ausencia'   => $ausencia,
                'previstos'  => $previstos,

                /**
                 * Diferencia entre lo fichado y lo previsto.
                 *
                 * Solo tiene sentido en dias con jornada prevista: un
                 * domingo trabajado son horas extra enteras, no una
                 * desviacion del cero.
                 */
                'desviacion' => $previstos > 0 ? $calculo['minutos'] - $previstos : 0,
                'extra'      => $previstos === 0 ? $calculo['minutos'] : 0,
            ];

            $totalMinutos += $calculo['minutos'];
            $totalPrevisto += $previstos;
            $totalExtra += $previstos === 0 ? $calculo['minutos'] : 0;

            if ($calculo['incompleta']) {
                $diasIncompletos++;
            }

            if ($ausencia && $delDia->isEmpty()) {
                $diasAusencia++;
            }

            $fecha->addDay();
        }

        $diasTrabajados = collect($dias)->where('trabajado', true)->count();

        return [
            'dias'             => $dias,
            'total_minutos'    => $totalMinutos,
            'dias_trabajados'  => $diasTrabajados,
            'dias_incompletos' => $diasIncompletos,
            'dias_ausencia'    => $diasAusencia,
            'total_previsto'   => $totalPrevisto,
            'total_extra'      => $totalExtra,
            'desviacion'       => $totalMinutos - $totalPrevisto,
            'media_diaria'     => $diasTrabajados > 0 ? (int) round($totalMinutos / $diasTrabajados) : 0,
            'desde'            => $desde,
            'hasta'            => $hasta,
        ];
    }

    // ------------------------------------------------------------------
    // Correcciones
    // ------------------------------------------------------------------

    /**
     * Corrige un fichaje sin borrarlo.
     *
     * El original se marca como anulado con su motivo, y se crea uno
     * nuevo que lo sustituye. La Inspección puede ver los dos.
     */
    public function corregir(Fichaje $original, Carbon $nuevaHora, string $motivo, Usuario $responsable): Fichaje
    {
        if ($original->anulado) {
            throw new RuntimeException('Ese fichaje ya fue corregido.');
        }

        if (blank(trim($motivo))) {
            throw new RuntimeException('Hay que indicar el motivo de la corrección.');
        }

        $corregido = Fichaje::create([
            'usuario_id'     => $original->usuario_id,
            'fecha'          => $nuevaHora->toDateString(),
            'fecha_hora'     => $nuevaHora,
            'tipo'           => $original->tipo,
            'origen'         => 'MANUAL',
            'corrige_a_id'   => $original->id,
            'motivo_correccion' => $motivo,
            'registrado_por' => $responsable->id,
            'ip'             => request()?->ip(),
        ]);

        $original->forceFill([
            'anulado'           => true,
            'anulado_por'       => $responsable->id,
            'anulado_en'        => now(),
            'motivo_correccion' => $motivo,
        ])->save();

        Auditoria::registrar('fichaje_corregido', 'fichajes', $corregido->id, [
            'original'  => $original->fecha_hora->format('d/m/Y H:i'),
            'corregido' => $nuevaHora->format('d/m/Y H:i'),
            'motivo'    => $motivo,
        ], $responsable->id);

        return $corregido;
    }

    /** Añade un fichaje olvidado. Queda marcado como MANUAL. */
    public function anadirManual(
        Usuario $usuario,
        string $tipo,
        Carbon $momento,
        string $motivo,
        Usuario $responsable,
    ): Fichaje {
        if (blank(trim($motivo))) {
            throw new RuntimeException('Hay que indicar por qué se añade este fichaje a mano.');
        }

        $fichaje = Fichaje::create([
            'usuario_id'        => $usuario->id,
            'fecha'             => $momento->toDateString(),
            'fecha_hora'        => $momento,
            'tipo'              => strtoupper($tipo),
            'origen'            => 'MANUAL',
            'motivo_correccion' => $motivo,
            'registrado_por'    => $responsable->id,
            'ip'                => request()?->ip(),
        ]);

        Auditoria::registrar('fichaje_manual', 'fichajes', $fichaje->id, [
            'usuario' => $usuario->nombre,
            'tipo'    => $tipo,
            'hora'    => $momento->format('d/m/Y H:i'),
            'motivo'  => $motivo,
        ], $responsable->id);

        return $fichaje;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    public static function horasYMinutos(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return sprintf('%d h %02d min', $horas, $resto);
    }

    /** Quién está trabajando ahora mismo. */
    public function quienEstaDentro(): Collection
    {
        return Usuario::activos()->where('ficha_jornada', true)->get()
            ->filter(fn (Usuario $u) => $this->estado($u) !== 'FUERA')
            ->values();
    }
}
