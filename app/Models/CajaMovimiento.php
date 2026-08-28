<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
    protected $table = 'caja_movimientos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha'   => 'datetime',
            'importe' => 'decimal:2',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function scopeSinCerrar($query)
    {
        return $query->whereNull('cierre_id');
    }

    /** Las salidas restan del efectivo teórico. */
    public function importeConSigno(): float
    {
        return $this->tipo === 'SALIDA'
            ? -(float) $this->importe
            : (float) $this->importe;
    }
}
