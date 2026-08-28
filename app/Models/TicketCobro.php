<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCobro extends Model
{
    protected $table = 'ticket_cobros';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'importe'   => 'decimal:2',
            'entregado' => 'decimal:2',
            'cambio'    => 'decimal:2',
        ];
    }

    public const MEDIOS = [
        'EFECTIVO'      => 'Efectivo',
        'TARJETA'       => 'Tarjeta',
        'BIZUM'         => 'Bizum',
        'TRANSFERENCIA' => 'Transferencia',
        'ANTICIPO'      => 'Anticipo',
        'MONEDERO'      => 'Monedero',
        'BONO'          => 'Bono',
        'VALE'          => 'Vale',
    ];

    /** Medios que mueven dinero en el cajón: cuadran el arqueo. */
    public const EN_CAJA = ['EFECTIVO'];

    /**
     * Medios NO PRESENCIALES: el cliente pagó por internet y no está
     * delante.
     *
     * Solo estos se devuelven automáticamente. En un cobro presencial hay
     * una persona en el mostrador que decide cómo devolver —efectivo del
     * cajón, anulación en el datáfono, vale— y automatizarlo sería
     * quitarle una decisión que le corresponde.
     *
     * TARJETA es presencial: es el datáfono, no la pasarela.
     */
    public const NO_PRESENCIALES = ['ANTICIPO'];

    public function esNoPresencial(): bool
    {
        return in_array($this->medio, self::NO_PRESENCIALES, true)
            && $this->pago_online_id !== null;
    }

    public function pagoOnline()
    {
        return $this->belongsTo(PagoOnline::class, 'pago_online_id');
    }

    /** ¿Ya se devolvió este cobro? */
    public function estaDevuelto(): bool
    {
        return $this->devuelto_por_cobro_id !== null;
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function nombreMedio(): string
    {
        return self::MEDIOS[$this->medio] ?? $this->medio;
    }
}
