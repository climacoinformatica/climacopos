<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ArticuloFoto extends Model
{
    protected $table = 'articulo_fotos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['principal' => 'boolean'];
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * URL pública. tenant_asset() enruta a través del middleware de
     * tenancy, así que las fotos de una empresa no son accesibles
     * desde el subdominio de otra.
     */
    public function url(): string
    {
        return tenant_asset($this->ruta);
    }

    public function urlMini(): string
    {
        return tenant_asset($this->ruta_mini ?: $this->ruta);
    }

    /** Borra también los ficheros del disco. */
    public function borrarConFicheros(): void
    {
        Storage::disk('public')->delete(array_filter([$this->ruta, $this->ruta_mini]));

        $this->delete();
    }

    /** Deja esta foto como principal y quita la marca al resto. */
    public function marcarPrincipal(): void
    {
        static::where('articulo_id', $this->articulo_id)
              ->where('id', '!=', $this->id)
              ->update(['principal' => false]);

        $this->forceFill(['principal' => true])->save();
    }
}
