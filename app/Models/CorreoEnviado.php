<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorreoEnviado extends Model
{
    protected $table = 'correos_enviados';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime'];
    }

    public const ETIQUETAS = [
        'RESERVA_CONFIRMADA' => 'Cita confirmada',
        'RESERVA_PENDIENTE'  => 'Solicitud recibida',
        'RESERVA_CANCELADA'  => 'Cita cancelada',
        'RECORDATORIO'       => 'Recordatorio',
        'PAGO_RECIBIDO'      => 'Pago recibido',
        'DEVOLUCION'         => 'Devolución',
        'PRUEBA'             => 'Prueba',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->tipo] ?? $this->tipo;
    }

    public function scopeConError($query)
    {
        return $query->whereIn('estado', ['ERROR', 'SIN_CONFIGURAR']);
    }

    /** Limpieza: el registro no hace falta más de unos meses. */
    public static function purgar(int $meses = 6): int
    {
        return static::where('enviado_en', '<', now()->subMonths($meses))->delete();
    }
}
