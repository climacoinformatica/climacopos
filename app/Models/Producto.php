<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Producto extends Model
{
    use CentralConnection;

    protected $table = 'productos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'caracteristicas' => 'array',
            'precio_desde'    => 'decimal:2',
            'activo'          => 'boolean',
            'descargable'     => 'boolean',
        ];
    }

    public function versiones()
    {
        return $this->hasMany(ProductoVersion::class)->orderByDesc('publicada_el');
    }

    public function versionActual()
    {
        return $this->hasOne(ProductoVersion::class)->where('es_actual', true);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    public function esSaas(): bool
    {
        return $this->modalidad === 'SAAS';
    }

    /**
     * ¿Se puede descargar ahora mismo?
     *
     * Un producto instalable sin ninguna versión publicada no es
     * descargable, aunque esté marcado como tal: enseñar un botón que
     * lleva a un error es peor que no enseñarlo.
     */
    public function tieneDescarga(): bool
    {
        return ! $this->esSaas()
            && $this->descargable
            && $this->versionActual()->exists();
    }

    public function etiquetaModalidad(): string
    {
        return $this->esSaas() ? 'En la nube' : 'Se instala en tu equipo';
    }
}
