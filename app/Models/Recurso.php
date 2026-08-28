<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Recurso físico limitado: cabina, lavacabezas, aparato de láser...
 * El motor de huecos (Fase 3) impide que se reserven más citas
 * simultáneas de las unidades disponibles.
 */
class Recurso extends Model
{
    protected $table = 'recursos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
