<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Familia extends Model
{
    protected $table = 'familias';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visible_online' => 'boolean',
            'activa'         => 'boolean',
        ];
    }

    public function articulos()
    {
        return $this->hasMany(Articulo::class)->orderBy('orden')->orderBy('nombre');
    }

    public function subfamilias()
    {
        return $this->hasMany(self::class, 'familia_padre_id')->orderBy('orden');
    }

    public function padre()
    {
        return $this->belongsTo(self::class, 'familia_padre_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeRaiz($query)
    {
        return $query->whereNull('familia_padre_id');
    }

    public function scopeDeServicios($query)
    {
        return $query->whereIn('tipo', ['SERVICIO', 'AMBOS']);
    }

    public function scopeDeProductos($query)
    {
        return $query->whereIn('tipo', ['PRODUCTO', 'AMBOS']);
    }

    public function scopeVisiblesOnline($query)
    {
        return $query->where('activa', true)->where('visible_online', true);
    }

    public function nombreCompleto(): string
    {
        return $this->padre ? $this->padre->nombre . ' › ' . $this->nombre : $this->nombre;
    }

    /** No se puede borrar una familia con artículos o subfamilias dentro. */
    public function puedeBorrarse(): bool
    {
        return $this->articulos()->count() === 0
            && $this->subfamilias()->count() === 0;
    }
}
