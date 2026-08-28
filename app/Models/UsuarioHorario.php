<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioHorario extends Model
{
    protected $table = 'usuario_horarios';

    protected $guarded = [];

    public const DIAS = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function nombreDia(): string
    {
        return self::DIAS[$this->dia_semana] ?? '';
    }

    public function duracionMinutos(): int
    {
        return (int) round(
            (strtotime($this->hora_fin) - strtotime($this->hora_ini)) / 60
        );
    }
}
