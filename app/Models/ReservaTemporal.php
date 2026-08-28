<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Retención del hueco mientras el cliente completa el pago en el portal.
 * Sin esto, dos personas pueden pagar la misma hora.
 */
class ReservaTemporal extends Model
{
    protected $table = 'reservas_temporales';

    protected $guarded = [];

    public const MINUTOS_VALIDEZ = 15;

    protected function casts(): array
    {
        return [
            'fecha'     => 'date',
            'caduca_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $retencion) {
            $retencion->token ??= Str::random(64);
            $retencion->caduca_en ??= now()->addMinutes(self::MINUTOS_VALIDEZ);
        });
    }

    public function scopeVigentes($query)
    {
        return $query->where('caduca_en', '>', now());
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function haCaducado(): bool
    {
        return $this->caduca_en->isPast();
    }

    /** Limpieza periódica. Va al scheduler. */
    public static function purgarCaducadas(): int
    {
        return static::where('caduca_en', '<', now()->subHour())->delete();
    }
}
