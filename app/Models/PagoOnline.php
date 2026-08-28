<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PagoOnline extends Model
{
    protected $table = 'pagos_online';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'importe'             => 'decimal:2',
            'comision_plataforma' => 'decimal:2',
            'devuelto_importe'    => 'decimal:2',
            'caduca_en'           => 'datetime',
            'pagado_en'           => 'datetime',
            'devuelto_en'         => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pago) {
            $pago->uuid ??= (string) Str::uuid();
            $pago->referencia ??= 'PG-' . strtoupper(Str::random(10));
        });
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function scopePagados($query)
    {
        return $query->where('estado', 'PAGADO');
    }

    public function estaPagado(): bool
    {
        return $this->estado === 'PAGADO';
    }

    public function esDevolvible(): bool
    {
        return $this->estado === 'PAGADO'
            && (float) $this->devuelto_importe < (float) $this->importe;
    }

    public function pendienteDevolver(): float
    {
        return round((float) $this->importe - (float) $this->devuelto_importe, 2);
    }

    /** Lo que el salón recibe realmente, descontada nuestra comisión. */
    public function netoParaElSalon(): float
    {
        return round((float) $this->importe - (float) $this->comision_plataforma, 2);
    }

    public function haCaducado(): bool
    {
        return $this->estado === 'INICIADO'
            && $this->caduca_en
            && $this->caduca_en->isPast();
    }

    public const ETIQUETAS = [
        'INICIADO'         => 'Pendiente de pago',
        'PAGADO'           => 'Pagado',
        'FALLIDO'          => 'Fallido',
        'CADUCADO'         => 'Caducado',
        'DEVUELTO'         => 'Devuelto',
        'DEVUELTO_PARCIAL' => 'Devuelto en parte',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->estado] ?? $this->estado;
    }
}
