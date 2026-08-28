<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BloqueoAgenda extends Model
{
    protected $table = 'bloqueos_agenda';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function scopeDelDia($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    /** Bloqueos sin usuario afectan a todo el salón. */
    public function esGeneral(): bool
    {
        return is_null($this->usuario_id);
    }
}
