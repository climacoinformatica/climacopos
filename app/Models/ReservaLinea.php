<?php

namespace App\Models;

use App\Support\Intervalo;
use Illuminate\Database\Eloquent\Model;

class ReservaLinea extends Model
{
    protected $table = 'reserva_lineas';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['precio' => 'decimal:2'];
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class);
    }

    public function duracionTotal(): int
    {
        return (int) $this->duracion_min + (int) $this->tiempo_pausa_min + (int) $this->tiempo_final_min;
    }

    public function horaFin(): string
    {
        return Intervalo::aHora(Intervalo::aMinutos($this->hora_ini) + $this->duracionTotal());
    }

    /**
     * Tramos en que el profesional está realmente trabajando.
     * Durante la pausa NO está ocupado.
     */
    public function tramosActivos(): array
    {
        $ini = Intervalo::aMinutos($this->hora_ini);

        if ((int) $this->tiempo_pausa_min <= 0) {
            return [new Intervalo($ini, $ini + $this->duracion_min + $this->tiempo_final_min)];
        }

        $tramos = [new Intervalo($ini, $ini + $this->duracion_min)];

        if ((int) $this->tiempo_final_min > 0) {
            $arranque = $ini + $this->duracion_min + $this->tiempo_pausa_min;
            $tramos[] = new Intervalo($arranque, $arranque + $this->tiempo_final_min);
        }

        return $tramos;
    }
}
