<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Terminal extends Model
{
    protected $table = 'terminales';

    protected $guarded = [];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'activo'                 => 'boolean',
            'agente_ultima_conexion' => 'datetime',
        ];
    }

    public function vinculos()
    {
        return $this->hasMany(TerminalVinculo::class);
    }

    public function config()
    {
        return $this->hasMany(TerminalConfig::class);
    }

    /** Lee un valor de configuracion del terminal con valor por defecto. */
    public function ajuste(string $clave, $porDefecto = null)
    {
        return $this->config()->where('clave', $clave)->value('valor') ?? $porDefecto;
    }

    public function fijarAjuste(string $clave, $valor): void
    {
        $this->config()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
