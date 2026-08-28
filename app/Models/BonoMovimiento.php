<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonoMovimiento extends Model
{
    protected $table = 'bono_movimientos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sesiones' => 'decimal:2',
            'importe'  => 'decimal:2',
            'fecha'    => 'datetime',
        ];
    }

    public function bono()
    {
        return $this->belongsTo(Bono::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
