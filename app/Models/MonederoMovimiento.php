<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonederoMovimiento extends Model
{
    protected $table = 'monedero_movimientos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'importe'       => 'decimal:2',
            'saldo_despues' => 'decimal:2',
            'fecha'         => 'datetime',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public const ETIQUETAS = [
        'RECARGA'    => 'Recarga',
        'GASTO'      => 'Gasto',
        'DEVOLUCION' => 'Devolución',
        'AJUSTE'     => 'Ajuste',
        'REGALO'     => 'Regalo',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->tipo] ?? $this->tipo;
    }
}
