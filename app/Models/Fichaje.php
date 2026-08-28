<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Fichaje de entrada, salida o pausa.
 *
 * TABLA INMUTABLE, como los registros de VERI*FACTU y por la misma razón:
 * un registro de jornada que se puede editar sin dejar rastro no prueba
 * nada, y el Real Decreto en tramitación exige explícitamente que sea
 * trazable e inmutable.
 *
 * Corregir un fichaje crea uno nuevo y marca el anterior como anulado.
 */
class Fichaje extends Model
{
    protected $table = 'fichajes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha'      => 'date',
            'fecha_hora' => 'datetime',
            'anulado'    => 'boolean',
            'anulado_en' => 'datetime',
        ];
    }

    public const TIPOS = [
        'ENTRADA'      => 'Entrada',
        'SALIDA'       => 'Salida',
        'PAUSA_INICIO' => 'Inicio de pausa',
        'PAUSA_FIN'    => 'Fin de pausa',
    ];

    protected static function booted(): void
    {
        /**
         * Solo se permite tocar los campos de anulación.
         *
         * Cambiar la hora de un fichaje ya registrado es exactamente lo
         * que el reglamento quiere impedir.
         */
        static::updating(function (self $fichaje) {
            $protegidos = ['usuario_id', 'fecha', 'fecha_hora', 'tipo', 'origen'];

            foreach ($protegidos as $campo) {
                if ($fichaje->isDirty($campo)) {
                    throw new RuntimeException(
                        "Un fichaje no se puede modificar. Para corregirlo, "
                        . "registra la corrección: así queda constancia de "
                        . "quién la hizo y por qué."
                    );
                }
            }
        });

        static::deleting(function () {
            throw new RuntimeException(
                'Los fichajes no se pueden borrar: hay que conservarlos '
                . 'cuatro años a disposición de la Inspección de Trabajo.'
            );
        });
    }

    // ------------------------------------------------------------------

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }

    public function corrigeA()
    {
        return $this->belongsTo(self::class, 'corrige_a_id');
    }

    public function correccion()
    {
        return $this->hasOne(self::class, 'corrige_a_id');
    }

    public function anuladoPor()
    {
        return $this->belongsTo(Usuario::class, 'anulado_por');
    }

    /** Los fichajes que cuentan: los no anulados. */
    public function scopeValidos(Builder $q): Builder
    {
        return $q->where('anulado', false);
    }

    public function scopeDelDia(Builder $q, $fecha): Builder
    {
        return $q->whereDate('fecha', $fecha);
    }

    public function scopeDeUsuario(Builder $q, int $usuarioId): Builder
    {
        return $q->where('usuario_id', $usuarioId);
    }

    // ------------------------------------------------------------------

    public function etiqueta(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function hora(): string
    {
        return $this->fecha_hora->format('H:i');
    }

    public function esEntrada(): bool
    {
        return $this->tipo === 'ENTRADA';
    }

    public function esCorreccion(): bool
    {
        return $this->corrige_a_id !== null;
    }

    /** ¿Lo metió un responsable en lugar de la persona trabajadora? */
    public function esManual(): bool
    {
        return $this->origen === 'MANUAL';
    }
}
