<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Ausencia extends Model
{
    protected $table = 'ausencias';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'desde'           => 'date',
            'hasta'           => 'date',
            'resuelta_en'     => 'datetime',
            'dias_computados' => 'decimal:1',
        ];
    }

    public const TIPOS = [
        'VACACIONES'      => 'Vacaciones',
        'BAJA'            => 'Baja médica',
        'PERMISO'         => 'Permiso retribuido',
        'ASUNTOS_PROPIOS' => 'Asuntos propios',
        'MATERNIDAD'      => 'Maternidad o paternidad',
        'FORMACION'       => 'Formación',
        'OTRO'            => 'Otro',
    ];

    /**
     * Tipos que descuentan del cupo anual.
     *
     * Una baja médica no gasta vacaciones: es exactamente lo contrario,
     * y descontarla sería un error con consecuencias laborales.
     */
    public const CONSUMEN_CUPO = ['VACACIONES', 'ASUNTOS_PROPIOS'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function resueltaPor()
    {
        return $this->belongsTo(Usuario::class, 'resuelta_por');
    }

    public function excepcion()
    {
        return $this->belongsTo(UsuarioExcepcion::class, 'excepcion_id');
    }

    // ------------------------------------------------------------------

    public function scopeAprobadas(Builder $q): Builder
    {
        return $q->where('estado', 'APROBADA');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('estado', 'SOLICITADA');
    }

    /** Ausencias que cubren una fecha concreta. */
    public function scopeEnFecha(Builder $q, $fecha): Builder
    {
        $fecha = Carbon::parse($fecha)->toDateString();

        return $q->where('desde', '<=', $fecha)->where('hasta', '>=', $fecha);
    }

    public function scopeDelAno(Builder $q, int $ano): Builder
    {
        return $q->whereYear('desde', $ano);
    }

    // ------------------------------------------------------------------

    public function etiqueta(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function consumeCupo(): bool
    {
        return in_array($this->tipo, self::CONSUMEN_CUPO, true);
    }

    public function esMedioDia(): bool
    {
        return $this->medio_dia !== null;
    }

    /** Días naturales que abarca, no los computados. */
    public function diasNaturales(): int
    {
        return (int) $this->desde->diffInDays($this->hasta) + 1;
    }

    public function cubre($fecha): bool
    {
        $fecha = Carbon::parse($fecha)->startOfDay();

        return $fecha->betweenIncluded(
            $this->desde->copy()->startOfDay(),
            $this->hasta->copy()->startOfDay(),
        );
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === 'APROBADA' && $this->cubre(now());
    }

    public function esFutura(): bool
    {
        return $this->desde->isFuture();
    }

    public function resumenFechas(): string
    {
        if ($this->desde->isSameDay($this->hasta)) {
            return $this->desde->format('d/m/Y')
                . ($this->esMedioDia() ? ' (' . strtolower($this->medio_dia) . ')' : '');
        }

        return $this->desde->format('d/m/Y') . ' – ' . $this->hasta->format('d/m/Y');
    }
}
