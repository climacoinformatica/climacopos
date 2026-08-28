<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketLinea extends Model
{
    protected $table = 'ticket_lineas';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad'      => 'decimal:3',
            'precio'        => 'decimal:2',
            'dto_pct'       => 'decimal:2',
            'impuesto_pct'  => 'decimal:2',
            'base'          => 'decimal:2',
            'impuesto'      => 'decimal:2',
            'importe'       => 'decimal:2',
            'es_invitacion' => 'boolean',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    /** Bono con el que se pago esta linea, si fue el caso. */
    public function bono()
    {
        return $this->belongsTo(Bono::class);
    }

    public function pagadaConBono(): bool
    {
        return $this->bono_id !== null;
    }

    /**
     * Recalcula los importes de la linea.
     *
     * El precio SIEMPRE lleva impuesto incluido (decision de la Fase 2),
     * asi que el importe se calcula primero y la base se saca hacia atras.
     * Al reves, los redondeos hacen que un corte de 22,00 se muestre
     * unas veces a 21,99 y otras a 22,01.
     */
    public function calcular(): self
    {
        $bruto = (float) $this->cantidad * (float) $this->precio;
        $importe = $this->es_invitacion
            ? 0.0
            : round($bruto * (1 - ((float) $this->dto_pct / 100)), 2);

        $base = round($importe / (1 + ((float) $this->impuesto_pct / 100)), 2);

        $this->importe  = $importe;
        $this->base     = $base;
        $this->impuesto = round($importe - $base, 2);

        return $this;
    }
}
