<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColaImpresion extends Model
{
    protected $table = 'cola_impresion';

    protected $guarded = [];

    /** Tras 5 intentos fallidos se deja de reintentar. */
    public const MAX_INTENTOS = 5;

    /** Un trabajo recogido y sin confirmar más de 2 minutos se reencola. */
    public const MINUTOS_ATASCADO = 2;

    protected function casts(): array
    {
        return [
            'recogido_en'  => 'datetime',
            'procesado_en' => 'datetime',
        ];
    }

    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE')
                     ->where('intentos', '<', self::MAX_INTENTOS);
    }

    /**
     * Trabajos listos para que los recoja el agente.
     *
     * Incluye los que se entregaron pero nadie confirmó: si el PC se
     * apagó justo después de recoger el trabajo, el ticket no se imprimió
     * y hay que volver a intentarlo.
     */
    public function scopeParaAgente($query, int $terminalId)
    {
        return $query->where('terminal_id', $terminalId)
            ->where('intentos', '<', self::MAX_INTENTOS)
            ->where(function ($q) {
                $q->where('estado', 'PENDIENTE')
                  ->orWhere(function ($sub) {
                      $sub->where('estado', 'ENVIADO')
                          ->where('recogido_en', '<', now()->subMinutes(self::MINUTOS_ATASCADO));
                  });
            })
            ->orderBy('id');
    }

    public function marcarRecogido(): void
    {
        $this->forceFill([
            'estado'      => 'ENVIADO',
            'recogido_en' => now(),
            'intentos'    => $this->intentos + 1,
        ])->save();
    }

    public function marcarHecho(): void
    {
        $this->forceFill([
            'estado'       => 'HECHO',
            'procesado_en' => now(),
            'error'        => null,
        ])->save();
    }

    public function marcarError(string $mensaje): void
    {
        $this->forceFill([
            'estado'       => $this->intentos >= self::MAX_INTENTOS ? 'ERROR' : 'PENDIENTE',
            'error'        => $mensaje,
            'procesado_en' => now(),
        ])->save();
    }

    /** Limpieza: los trabajos hechos no hacen falta más de unos días. */
    public static function purgar(int $dias = 7): int
    {
        return static::where('estado', 'HECHO')
            ->where('procesado_en', '<', now()->subDays($dias))
            ->delete();
    }
}
