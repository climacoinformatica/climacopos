<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Vale extends Model
{
    protected $table = 'vales';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'importe_inicial'  => 'decimal:2',
            'importe_restante' => 'decimal:2',
            'emitido_el'       => 'date',
            'caduca_el'        => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $vale) {
            /**
             * Código sin caracteres ambiguos.
             *
             * Se dicta por teléfono y se teclea a mano, así que fuera 0/O
             * y 1/I/L: en un papel impreso en térmica son indistinguibles.
             */
            $vale->codigo ??= 'V-' . self::codigoLegible(8);
            $vale->emitido_el ??= now()->toDateString();
            $vale->importe_restante ??= $vale->importe_inicial;
        });
    }

    protected static function codigoLegible(int $longitud): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $codigo = '';

        for ($i = 0; $i < $longitud; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $codigo;
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ticketOrigen()
    {
        return $this->belongsTo(Ticket::class, 'ticket_origen_id');
    }

    public function scopeUtilizables($query)
    {
        return $query->where('estado', 'ACTIVO')
            ->where('importe_restante', '>', 0)
            ->where(function ($q) {
                $q->whereNull('caduca_el')->orWhere('caduca_el', '>=', now()->toDateString());
            });
    }

    public function haCaducado(): bool
    {
        return $this->caduca_el && $this->caduca_el->isPast();
    }

    public function estaDisponible(): bool
    {
        return $this->estado === 'ACTIVO'
            && ! $this->haCaducado()
            && (float) $this->importe_restante > 0.001;
    }
}
