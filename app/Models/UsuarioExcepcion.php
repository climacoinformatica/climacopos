<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioExcepcion extends Model
{
    protected $table = 'usuario_excepciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha_ini' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    /** Excepciones que afectan a toda la empresa, no a un profesional. */
    public function scopeDeEmpresa($query)
    {
        return $query->whereNull('usuario_id');
    }

    public function scopeEnFecha($query, $fecha)
    {
        return $query->where('fecha_ini', '<=', $fecha)
                     ->where('fecha_fin', '>=', $fecha);
    }

    /** Si es true, ese dia no se trabaja en absoluto. */
    public function bloqueaJornadaCompleta(): bool
    {
        return $this->tipo !== 'HORARIO_ESPECIAL';
    }
}
