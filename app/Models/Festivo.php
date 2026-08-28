<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Festivo extends Model
{
    protected $table = 'festivos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public const AMBITOS = [
        'NACIONAL'   => 'Nacional',
        'AUTONOMICO' => 'Autonómico',
        'LOCAL'      => 'Local',
        'CIERRE'     => 'Cierre del salón',
    ];

    public function excepcion()
    {
        return $this->belongsTo(UsuarioExcepcion::class, 'excepcion_id');
    }

    public function scopeDelAno(Builder $q, int $ano): Builder
    {
        return $q->whereYear('fecha', $ano);
    }

    public function scopeProximos(Builder $q, int $dias = 60): Builder
    {
        return $q->whereBetween('fecha', [
            now()->toDateString(),
            now()->addDays($dias)->toDateString(),
        ]);
    }

    public function etiquetaAmbito(): string
    {
        return self::AMBITOS[$this->ambito] ?? $this->ambito;
    }

    public function cierraTodoElDia(): bool
    {
        return $this->media_jornada === null;
    }

    public function esPasado(): bool
    {
        return $this->fecha->isPast() && ! $this->fecha->isToday();
    }

    public function diasParaLlegar(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha, false);
    }
}
