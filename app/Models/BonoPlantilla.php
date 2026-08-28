<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonoPlantilla extends Model
{
    use SoftDeletes;

    protected $table = 'bonos_plantillas';

    protected $guarded = [];

    protected $attributes = [
        'modalidad'      => 'SESIONES',
        'impuesto_pct'   => 0,
        'activo'         => true,
        'vender_online'  => false,
        'color'          => '#8b5cf6',
        'orden'          => 0,
    ];

    protected function casts(): array
    {
        return [
            'precio'         => 'decimal:2',
            'impuesto_pct'   => 'decimal:2',
            'saldo_otorgado' => 'decimal:2',
            'activo'         => 'boolean',
            'vender_online'  => 'boolean',
        ];
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function familia()
    {
        return $this->belongsTo(Familia::class);
    }

    public function bonos()
    {
        return $this->hasMany(Bono::class, 'plantilla_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function esDeSesiones(): bool
    {
        return $this->modalidad === 'SESIONES';
    }

    /**
     * Ahorro respecto a pagar suelto.
     * Es el argumento de venta, así que conviene tenerlo calculado.
     */
    public function ahorro(): ?float
    {
        if ($this->esDeSesiones()) {
            if (! $this->articulo || ! $this->num_sesiones) {
                return null;
            }

            $suelto = (float) $this->articulo->precio * $this->num_sesiones;

            return round($suelto - (float) $this->precio, 2);
        }

        return round((float) ($this->saldo_otorgado ?? 0) - (float) $this->precio, 2);
    }

    public function precioPorSesion(): ?float
    {
        return $this->esDeSesiones() && $this->num_sesiones > 0
            ? round((float) $this->precio / $this->num_sesiones, 2)
            : null;
    }

    /** ¿Este bono cubre este artículo? */
    public function cubre(Articulo $articulo): bool
    {
        if ($this->articulo_id) {
            return $this->articulo_id === $articulo->id;
        }

        if ($this->familia_id) {
            return $this->familia_id === $articulo->familia_id;
        }

        // Sin restricción: vale para cualquier servicio
        return true;
    }
}
