<?php

namespace App\Models;

use App\Support\Permisos;
use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'perfiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'permisos'   => 'array',
            'es_sistema' => 'boolean',
        ];
    }

    public function usuarios()
    {
        return $this->hasMany(Usuario::class);
    }

    public function tienePermiso(string $clave): bool
    {
        return in_array($clave, $this->permisos ?? [], true);
    }

    /** Descarta claves que ya no existen en el catalogo. */
    public function setPermisosAttribute(array $valor): void
    {
        $this->attributes['permisos'] = json_encode(
            array_values(array_filter($valor, fn ($p) => Permisos::existe($p)))
        );
    }

    public function puedeBorrarse(): bool
    {
        return ! $this->es_sistema && $this->usuarios()->count() === 0;
    }
}
