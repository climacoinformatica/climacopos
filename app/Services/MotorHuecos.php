<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\BloqueoAgenda;
use App\Models\Reserva;
use App\Models\ReservaTemporal;
use App\Models\Usuario;
use App\Models\UsuarioExcepcion;
use App\Models\UsuarioHorario;
use App\Support\Intervalo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Motor de disponibilidad.
 *
 * Es la pieza central del producto: calcula qué horas puede reservar un
 * cliente teniendo en cuenta, a la vez:
 *
 *   - horario del profesional para ese día de la semana
 *   - excepciones (vacaciones, bajas, festivos, cierres, horarios especiales)
 *   - citas ya existentes, respetando la PAUSA intermedia
 *   - bloqueos manuales de la agenda
 *   - retenciones temporales de clientes que están pagando ahora mismo
 *   - recursos físicos limitados (cabinas, lavacabezas)
 *   - antelación mínima y máxima configuradas
 *
 * La clave del negocio está en la pausa: durante los 30 minutos que la
 * clienta espera con el tinte puesto, el profesional está LIBRE y puede
 * atender a otra persona. Un motor que no lo contemple pierde una parte
 * importante de la facturación del salón.
 */
class MotorHuecos
{
    /** Rejilla de inicio de citas, en minutos. */
    public const PASO = 15;

    /** Estados que ocupan sitio en la agenda. */
    public const ESTADOS_OCUPAN = ['PENDIENTE', 'CONFIRMADA', 'EN_CURSO'];

    /**
     * Horas a las que se puede empezar el servicio.
     *
     * @return string[] ['09:00', '09:15', ...]
     */
    public function huecos(
        Carbon $fecha,
        Articulo $servicio,
        ?Usuario $profesional = null,
        bool $desdePortal = true,
    ): array {
        $candidatos = $profesional
            ? collect([$profesional])
            : $this->profesionalesDe($servicio);

        $horas = collect();

        foreach ($candidatos as $candidato) {
            $horas = $horas->merge($this->huecosDe($fecha, $servicio, $candidato, $desdePortal));
        }

        return $horas->unique()->sort()->values()->all();
    }

    /**
     * Huecos con el profesional que los atiende. Útil para "el primero
     * disponible": el portal necesita saber a quién asignar la cita.
     *
     * @return array<string, int[]> ['09:00' => [3, 7], ...]
     */
    public function huecosConProfesional(
        Carbon $fecha,
        Articulo $servicio,
        ?Usuario $profesional = null,
        bool $desdePortal = true,
    ): array {
        $candidatos = $profesional ? collect([$profesional]) : $this->profesionalesDe($servicio);
        $mapa = [];

        foreach ($candidatos as $candidato) {
            foreach ($this->huecosDe($fecha, $servicio, $candidato, $desdePortal) as $hora) {
                $mapa[$hora][] = $candidato->id;
            }
        }

        ksort($mapa);

        return $mapa;
    }

    /** Huecos de un profesional concreto. */
    public function huecosDe(
        Carbon $fecha,
        Articulo $servicio,
        Usuario $profesional,
        bool $desdePortal = true,
    ): array {
        $jornada = $this->jornadaDe($profesional, $fecha);

        if ($jornada === []) {
            return [];   // no trabaja ese día
        }

        $ocupados = $this->tramosOcupados($profesional, $fecha);
        $duracion = $servicio->duracionPara($profesional);
        $pausa    = (int) $servicio->tiempo_pausa_min;
        $final    = (int) $servicio->tiempo_final_min;

        $limiteMin = $desdePortal ? $this->minutoMinimo($fecha) : null;
        $huecos = [];

        foreach ($jornada as $tramo) {
            for ($inicio = $tramo->ini; $inicio + $duracion + $pausa + $final <= $tramo->fin; $inicio += self::PASO) {

                if ($limiteMin !== null && $inicio < $limiteMin) {
                    continue;
                }

                if (! $this->cabe($inicio, $duracion, $pausa, $final, $ocupados, $tramo)) {
                    continue;
                }

                if ($servicio->recurso_id && ! $this->hayRecurso($servicio, $fecha, $inicio, $duracion + $pausa + $final)) {
                    continue;
                }

                $huecos[] = Intervalo::aHora($inicio);
            }
        }

        return $huecos;
    }

    /**
     * ¿Cabe el servicio empezando a esta hora?
     *
     * Solo se comprueban los tramos ACTIVOS. Durante la pausa el
     * profesional puede estar ocupado con otra clienta y da igual.
     */
    protected function cabe(int $inicio, int $duracion, int $pausa, int $final, array $ocupados, Intervalo $jornada): bool
    {
        $tramosNuevos = $this->tramosActivos($inicio, $duracion, $pausa, $final);

        foreach ($tramosNuevos as $nuevo) {
            // El trabajo activo debe caber dentro de la jornada
            if (! $jornada->contiene($nuevo)) {
                return false;
            }

            foreach ($ocupados as $ocupado) {
                if ($nuevo->solapaCon($ocupado)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Tramos en que el profesional está realmente trabajando.
     * Sin pausa devuelve uno; con pausa, dos separados.
     *
     * @return Intervalo[]
     */
    public function tramosActivos(int $inicio, int $duracion, int $pausa, int $final): array
    {
        if ($pausa <= 0) {
            return [new Intervalo($inicio, $inicio + $duracion + $final)];
        }

        $tramos = [new Intervalo($inicio, $inicio + $duracion)];

        if ($final > 0) {
            $arranqueFinal = $inicio + $duracion + $pausa;
            $tramos[] = new Intervalo($arranqueFinal, $arranqueFinal + $final);
        }

        return $tramos;
    }

    // ------------------------------------------------------------------
    // Jornada del profesional
    // ------------------------------------------------------------------

    /**
     * Tramos en que el profesional trabaja ese día, ya descontados
     * festivos, vacaciones y cierres del salón.
     *
     * @return Intervalo[]
     */
    public function jornadaDe(Usuario $profesional, Carbon $fecha): array
    {
        $excepciones = $this->excepcionesDe($profesional, $fecha);

        // Cualquier excepción que no sea horario especial anula el día entero
        foreach ($excepciones as $excepcion) {
            if ($excepcion->bloqueaJornadaCompleta()) {
                return [];
            }
        }

        // Un horario especial sustituye al habitual
        $especiales = $excepciones->where('tipo', 'HORARIO_ESPECIAL');

        if ($especiales->isNotEmpty()) {
            return $especiales
                ->filter(fn ($e) => $e->hora_ini && $e->hora_fin)
                ->map(fn ($e) => Intervalo::desdeHoras($e->hora_ini, $e->hora_fin))
                ->values()->all();
        }

        return UsuarioHorario::where('usuario_id', $profesional->id)
            ->where('dia_semana', (int) $fecha->dayOfWeek)
            ->orderBy('hora_ini')
            ->get()
            ->map(fn ($h) => Intervalo::desdeHoras($h->hora_ini, $h->hora_fin))
            ->all();
    }

    protected function excepcionesDe(Usuario $profesional, Carbon $fecha): Collection
    {
        return UsuarioExcepcion::enFecha($fecha->toDateString())
            ->where(function ($q) use ($profesional) {
                $q->whereNull('usuario_id')            // afecta a todo el salón
                  ->orWhere('usuario_id', $profesional->id);
            })
            ->get();
    }

    // ------------------------------------------------------------------
    // Ocupación
    // ------------------------------------------------------------------

    /**
     * Todo lo que impide reservar con este profesional ese día:
     * citas (solo tramos activos), bloqueos y retenciones temporales.
     *
     * @return Intervalo[]
     */
    public function tramosOcupados(Usuario $profesional, Carbon $fecha): array
    {
        $ocupados = [];

        // --- Citas existentes
        $lineas = \App\Models\ReservaLinea::query()
            ->where('usuario_id', $profesional->id)
            ->whereHas('reserva', function ($q) use ($fecha) {
                $q->where('fecha', $fecha->toDateString())
                  ->whereIn('estado', self::ESTADOS_OCUPAN);
            })
            ->get();

        foreach ($lineas as $linea) {
            foreach ($this->tramosActivos(
                Intervalo::aMinutos($linea->hora_ini),
                (int) $linea->duracion_min,
                (int) $linea->tiempo_pausa_min,
                (int) $linea->tiempo_final_min,
            ) as $tramo) {
                $ocupados[] = $tramo;
            }
        }

        // --- Bloqueos manuales (suyos o de todo el salón)
        $bloqueos = BloqueoAgenda::where('fecha', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $profesional->id))
            ->get();

        foreach ($bloqueos as $bloqueo) {
            $ocupados[] = Intervalo::desdeHoras($bloqueo->hora_ini, $bloqueo->hora_fin);
        }

        // --- Huecos retenidos por clientes que están pagando ahora mismo
        $retenciones = ReservaTemporal::vigentes()
            ->where('fecha', $fecha->toDateString())
            ->where('usuario_id', $profesional->id)
            ->get();

        foreach ($retenciones as $retencion) {
            $ocupados[] = Intervalo::desdeHoras($retencion->hora_ini, $retencion->hora_fin);
        }

        return $ocupados;
    }

    // ------------------------------------------------------------------
    // Recursos limitados
    // ------------------------------------------------------------------

    protected function hayRecurso(Articulo $servicio, Carbon $fecha, int $inicio, int $duracionTotal): bool
    {
        $recurso = $servicio->recurso;

        if (! $recurso || ! $recurso->activo) {
            return true;
        }

        $nuevo = new Intervalo($inicio, $inicio + $duracionTotal);

        // Cuántas citas usan ya ese recurso en ese rato.
        // Aquí SÍ cuenta la pausa: la cabina sigue ocupada aunque el
        // profesional se haya ido a atender a otra persona.
        $enUso = \App\Models\ReservaLinea::query()
            ->where('recurso_id', $recurso->id)
            ->whereHas('reserva', function ($q) use ($fecha) {
                $q->where('fecha', $fecha->toDateString())
                  ->whereIn('estado', self::ESTADOS_OCUPAN);
            })
            ->get()
            ->filter(function ($linea) use ($nuevo) {
                $ini = Intervalo::aMinutos($linea->hora_ini);
                $total = (int) $linea->duracion_min + (int) $linea->tiempo_pausa_min + (int) $linea->tiempo_final_min;

                return (new Intervalo($ini, $ini + $total))->solapaCon($nuevo);
            })
            ->count();

        return $enUso < $recurso->cantidad;
    }

    // ------------------------------------------------------------------
    // Antelación
    // ------------------------------------------------------------------

    /** Minuto a partir del cual se admiten reservas desde el portal. */
    protected function minutoMinimo(Carbon $fecha): ?int
    {
        $antelacion = (int) config_empresa('antelacion_min_horas', 2);

        if (! $fecha->isToday()) {
            return $fecha->isPast() ? 24 * 60 : null;   // días pasados: nada
        }

        $limite = now()->addHours($antelacion);

        if (! $limite->isSameDay($fecha)) {
            return 24 * 60;   // el límite ya cae en otro día: hoy no hay nada
        }

        // Se redondea hacia arriba al siguiente paso de la rejilla
        $minutos = $limite->hour * 60 + $limite->minute;

        return (int) (ceil($minutos / self::PASO) * self::PASO);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** Profesionales que pueden realizar el servicio. Vacío = todos. */
    public function profesionalesDe(Articulo $servicio): Collection
    {
        $asignados = $servicio->profesionales()->where('estado', 'ACTIVO')->get();

        return $asignados->isNotEmpty()
            ? $asignados
            : Usuario::activos()->profesionales()->get();
    }

    /**
     * ¿Se puede colocar esta cita aquí? Se usa al crear o mover citas
     * desde el panel, donde no aplican las restricciones de antelación.
     */
    public function estaLibre(
        Carbon $fecha,
        string $hora,
        Articulo $servicio,
        Usuario $profesional,
        ?int $ignorarReservaId = null,
    ): bool {
        $jornada = $this->jornadaDe($profesional, $fecha);

        if ($jornada === []) {
            return false;
        }

        $ocupados = $this->tramosOcupados($profesional, $fecha);

        if ($ignorarReservaId) {
            $ocupados = $this->descontarReserva($ocupados, $ignorarReservaId, $profesional, $fecha);
        }

        $inicio = Intervalo::aMinutos($hora);

        foreach ($jornada as $tramo) {
            if ($this->cabe(
                $inicio,
                $servicio->duracionPara($profesional),
                (int) $servicio->tiempo_pausa_min,
                (int) $servicio->tiempo_final_min,
                $ocupados,
                $tramo,
            )) {
                return true;
            }
        }

        return false;
    }

    /** Al mover una cita, sus propios tramos no deben estorbarle. */
    protected function descontarReserva(array $ocupados, int $reservaId, Usuario $profesional, Carbon $fecha): array
    {
        $propios = [];

        foreach (\App\Models\ReservaLinea::where('reserva_id', $reservaId)
                     ->where('usuario_id', $profesional->id)->get() as $linea) {
            foreach ($this->tramosActivos(
                Intervalo::aMinutos($linea->hora_ini),
                (int) $linea->duracion_min,
                (int) $linea->tiempo_pausa_min,
                (int) $linea->tiempo_final_min,
            ) as $tramo) {
                $propios[] = (string) $tramo;
            }
        }

        return array_values(array_filter(
            $ocupados,
            fn (Intervalo $i) => ! in_array((string) $i, $propios, true)
        ));
    }

    /** Primer día con hueco a partir de una fecha. Para el portal. */
    public function primerDiaConHueco(
        Carbon $desde,
        Articulo $servicio,
        ?Usuario $profesional = null,
        int $maxDias = 60,
    ): ?Carbon {
        $fecha = $desde->copy()->startOfDay();

        for ($i = 0; $i < $maxDias; $i++) {
            if ($this->huecos($fecha, $servicio, $profesional) !== []) {
                return $fecha;
            }

            $fecha = $fecha->copy()->addDay();
        }

        return null;
    }
}
