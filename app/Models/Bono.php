<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bono extends Model
{
    protected $table = 'bonos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'comprado_el'   => 'date',
            'caduca_el'     => 'date',
            'saldo_inicial' => 'decimal:2',
            'saldo_actual'  => 'decimal:2',
            'precio_pagado' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $bono) {
            $bono->codigo ??= 'B-' . strtoupper(Str::random(8));
            $bono->comprado_el ??= now()->toDateString();
        });
    }

    public function plantilla()
    {
        return $this->belongsTo(BonoPlantilla::class, 'plantilla_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function movimientos()
    {
        return $this->hasMany(BonoMovimiento::class)->orderByDesc('fecha');
    }

    public function scopeUtilizables($query)
    {
        return $query->where('estado', 'ACTIVO')
            ->where(function ($q) {
                $q->whereNull('caduca_el')->orWhere('caduca_el', '>=', now()->toDateString());
            });
    }

    // ------------------------------------------------------------------

    public function sesionesRestantes(): int
    {
        return max(0, (int) $this->sesiones_totales - (int) $this->sesiones_usadas);
    }

    public function haCaducado(): bool
    {
        return $this->caduca_el && $this->caduca_el->isPast();
    }

    /**
     * ¿Se puede usar ahora?
     *
     * Se comprueba la caducidad en cada uso y no solo con la tarea
     * nocturna: si no, un bono caducado a medianoche se podría gastar
     * durante toda la mañana siguiente.
     */
    public function estaDisponible(): bool
    {
        if ($this->estado !== 'ACTIVO' || $this->haCaducado()) {
            return false;
        }

        return $this->modalidad === 'SESIONES'
            ? $this->sesionesRestantes() > 0
            : (float) $this->saldo_actual > 0.001;
    }

    public function cubre(Articulo $articulo): bool
    {
        return $this->estaDisponible()
            && $this->plantilla
            && $this->plantilla->cubre($articulo);
    }

    /** Lo que queda, en euros, para mostrarlo de forma uniforme. */
    public function valorRestante(): float
    {
        if ($this->modalidad === 'SALDO') {
            return (float) $this->saldo_actual;
        }

        $porSesion = $this->plantilla?->precioPorSesion() ?? 0;

        return round($this->sesionesRestantes() * $porSesion, 2);
    }

    public function resumen(): string
    {
        if ($this->modalidad === 'SALDO') {
            return number_format((float) $this->saldo_actual, 2, ',', '.') . ' € disponibles';
        }

        $restantes = $this->sesionesRestantes();

        return $restantes . ' de ' . $this->sesiones_totales
            . ' sesion' . ($restantes === 1 ? '' : 'es') . ' disponibles';
    }

    public function diasParaCaducar(): ?int
    {
        return $this->caduca_el ? (int) now()->diffInDays($this->caduca_el, false) : null;
    }
}
