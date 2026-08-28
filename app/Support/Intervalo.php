<?php

namespace App\Support;

/**
 * Intervalo de minutos desde medianoche.
 *
 * Toda la agenda trabaja con enteros (480 = 08:00) en lugar de objetos de
 * fecha. Es mucho más rápido para comparar solapes y elimina de raíz los
 * problemas de zona horaria dentro de un mismo día.
 */
class Intervalo
{
    public function __construct(
        public readonly int $ini,
        public readonly int $fin,
    ) {
    }

    public static function desdeHoras(string $ini, string $fin): self
    {
        return new self(self::aMinutos($ini), self::aMinutos($fin));
    }

    public static function desde(string $ini, int $duracion): self
    {
        $minutos = self::aMinutos($ini);

        return new self($minutos, $minutos + $duracion);
    }

    public static function aMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));

        return $h * 60 + $m;
    }

    public static function aHora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    public function duracion(): int
    {
        return $this->fin - $this->ini;
    }

    /**
     * Dos intervalos se solapan si uno empieza antes de que acabe el otro.
     * Tocarse en el extremo NO es solapar: una cita que acaba a las 10:00
     * y otra que empieza a las 10:00 conviven sin problema.
     */
    public function solapaCon(self $otro): bool
    {
        return $this->ini < $otro->fin && $otro->ini < $this->fin;
    }

    public function contiene(self $otro): bool
    {
        return $this->ini <= $otro->ini && $this->fin >= $otro->fin;
    }

    public function horaIni(): string
    {
        return self::aHora($this->ini);
    }

    public function horaFin(): string
    {
        return self::aHora($this->fin);
    }

    public function __toString(): string
    {
        return $this->horaIni() . '-' . $this->horaFin();
    }

    /** Resta un conjunto de intervalos a este, devolviendo los huecos libres. */
    public function menos(array $ocupados): array
    {
        $libres = [$this];

        foreach ($ocupados as $ocupado) {
            $resultado = [];

            foreach ($libres as $libre) {
                if (! $libre->solapaCon($ocupado)) {
                    $resultado[] = $libre;
                    continue;
                }

                // Trozo que queda por delante del ocupado
                if ($libre->ini < $ocupado->ini) {
                    $resultado[] = new self($libre->ini, min($libre->fin, $ocupado->ini));
                }

                // Trozo que queda por detrás
                if ($libre->fin > $ocupado->fin) {
                    $resultado[] = new self(max($libre->ini, $ocupado->fin), $libre->fin);
                }
            }

            $libres = $resultado;
        }

        return array_values(array_filter($libres, fn (self $i) => $i->duracion() > 0));
    }
}
