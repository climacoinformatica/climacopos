<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreJornada extends Model
{
    protected $table = 'cierres_jornada';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha_ini'               => 'datetime',
            'fecha_fin'               => 'datetime',
            'efectivo_inicial'        => 'decimal:2',
            'efectivo_teorico'        => 'decimal:2',
            'efectivo_contado'        => 'decimal:2',
            'descuadre'               => 'decimal:2',
            'total_ventas'            => 'decimal:2',
            'total_base'              => 'decimal:2',
            'total_impuesto'          => 'decimal:2',
            'totales_por_medio'       => 'array',
            'totales_por_familia'     => 'array',
            'totales_por_profesional' => 'array',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'cierre_id');
    }

    public function movimientos()
    {
        return $this->hasMany(CajaMovimiento::class, 'cierre_id');
    }

    /** El último cierre marca desde cuándo cuenta la jornada actual. */
    public static function ultimo(): ?self
    {
        return self::orderByDesc('fecha_fin')->first();
    }

    public function hayDescuadre(): bool
    {
        return abs((float) $this->descuadre) >= 0.01;
    }

    public function ticketMedio(): float
    {
        return $this->num_tickets > 0
            ? round((float) $this->total_ventas / $this->num_tickets, 2)
            : 0.0;
    }
}
