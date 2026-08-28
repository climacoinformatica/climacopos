<?php

namespace App\Models;

use App\Support\Intervalo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reserva extends Model
{
    protected $table = 'reservas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha'                => 'date',
            'importe_total'        => 'decimal:2',
            'importe_pagado'       => 'decimal:2',
            'confirmada_en'        => 'datetime',
            'cancelada_en'         => 'datetime',
            'recordatorio_enviado' => 'boolean',
        ];
    }

    /** Estados que ocupan sitio en la agenda. */
    public const OCUPAN = ['PENDIENTE', 'CONFIRMADA', 'EN_CURSO'];

    /** Estados que ya no admiten cambios. */
    public const CERRADOS = ['RECHAZADA', 'CANCELADA', 'ATENDIDA', 'NO_SHOW'];

    public const COLORES = [
        'PENDIENTE'  => '#f59e0b',
        'CONFIRMADA' => '#6366f1',
        'EN_CURSO'   => '#10b981',
        'ATENDIDA'   => '#64748b',
        'NO_SHOW'    => '#ef4444',
        'CANCELADA'  => '#475569',
        'RECHAZADA'  => '#475569',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reserva) {
            $reserva->uuid ??= (string) Str::uuid();
            $reserva->codigo ??= self::generarCodigo();
        });
    }

    /** Código corto y legible por teléfono: RS-8F3K2 */
    public static function generarCodigo(): string
    {
        do {
            // Sin I, O, 0 ni 1: se confunden al dictarlos
            $codigo = 'RS-' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);
        } while (self::where('codigo', $codigo)->exists());

        return $codigo;
    }

    // ------------------------------------------------------------------

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lineas()
    {
        return $this->hasMany(ReservaLinea::class)->orderBy('orden');
    }

    /**
     * Ticket generado al cobrar la cita.
     *
     * OJO con el scope de formación: el modelo Ticket excluye por defecto
     * los documentos de prácticas, así que una cita cobrada por un
     * empleado en formación seguirá apareciendo como pendiente de cobro
     * en el TPV. Es el comportamiento correcto: esos cobros no son ventas.
     */
    public function ticket()
    {
        return $this->hasOne(Ticket::class);
    }

    public function pagos()
    {
        return $this->hasMany(PagoOnline::class);
    }

    /** Lo que el cliente ya ha pagado por adelantado. */
    public function anticipo(): float
    {
        return round((float) $this->pagos()->pagados()->sum('importe')
                   - (float) $this->pagos()->sum('devuelto_importe'), 2);
    }

    public function tieneAnticipo(): bool
    {
        return $this->anticipo() > 0.001;
    }

    public function creadaPor()
    {
        return $this->belongsTo(Usuario::class, 'creada_por');
    }

    public function confirmadaPor()
    {
        return $this->belongsTo(Usuario::class, 'confirmada_por');
    }

    // ------------------------------------------------------------------

    public function scopeOcupan($query)
    {
        return $query->whereIn('estado', self::OCUPAN);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopeDelDia($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function scopeEntre($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    // ------------------------------------------------------------------
    // Transiciones de estado
    // ------------------------------------------------------------------

    public function confirmar(?Usuario $usuario = null): bool
    {
        if ($this->estado !== 'PENDIENTE') {
            return false;
        }

        $this->update([
            'estado'         => 'CONFIRMADA',
            'confirmada_por' => $usuario?->id,
            'confirmada_en'  => now(),
        ]);

        // Aviso al cliente, si el salon lo tiene activado
        if (tenant('avisar_reserva')) {
            (new \App\Services\Correo\GestorCorreos())->reservaConfirmada($this->fresh());
        }

        Auditoria::registrar('reserva_confirmada', 'reservas', $this->id, ['codigo' => $this->codigo]);

        return true;
    }

    public function rechazar(string $motivo, ?Usuario $usuario = null): bool
    {
        if ($this->estado !== 'PENDIENTE') {
            return false;
        }

        $this->update([
            'estado'         => 'RECHAZADA',
            'motivo_rechazo' => $motivo,
            'confirmada_por' => $usuario?->id,
            'confirmada_en'  => now(),
        ]);

        if (tenant('avisar_cancelacion')) {
            (new \App\Services\Correo\GestorCorreos())->reservaCancelada($this->fresh(), $motivo);
        }

        Auditoria::registrar('reserva_rechazada', 'reservas', $this->id,
            ['codigo' => $this->codigo, 'motivo' => $motivo]);

        /**
         * Devolución automática.
         *
         * Si el salón rechaza una reserva, el dinero vuelve sin que nadie
         * tenga que acordarse. Quedarse una fianza de una cita que no se
         * va a prestar no tiene defensa posible.
         */
        if ($this->tieneAnticipo()) {
            (new \App\Services\Pagos\GestorPagos())->devolver($this, 'Reserva rechazada: ' . $motivo);
        }

        return true;
    }

    public function cancelar(string $por = 'SALON'): bool
    {
        if (in_array($this->estado, self::CERRADOS, true)) {
            return false;
        }

        $this->update([
            'estado'        => 'CANCELADA',
            'cancelada_por' => $por,
            'cancelada_en'  => now(),
        ]);

        if (tenant('avisar_cancelacion')) {
            (new \App\Services\Correo\GestorCorreos())->reservaCancelada($this->fresh(), $motivo);
        }

        Auditoria::registrar('reserva_cancelada', 'reservas', $this->id,
            ['codigo' => $this->codigo, 'por' => $por]);

        /**
         * Devolución según la política del salón.
         *
         * Si cancela el SALÓN, se devuelve siempre: el cliente no ha
         * hecho nada mal. Si cancela el CLIENTE, solo dentro de plazo.
         */
        if ($this->tieneAnticipo() && ($por === 'SALON' || $this->enPlazoDeCancelacion())) {
            (new \App\Services\Pagos\GestorPagos())->devolver(
                $this,
                $por === 'SALON' ? 'Cancelada por el salón' : 'Cancelada por el cliente en plazo',
            );
        }

        return true;
    }

    public function marcarNoShow(): bool
    {
        if (! in_array($this->estado, ['CONFIRMADA', 'EN_CURSO'], true)) {
            return false;
        }

        $this->update(['estado' => 'NO_SHOW']);
        $this->cliente?->increment('no_shows');

        Auditoria::registrar('reserva_no_show', 'reservas', $this->id, ['codigo' => $this->codigo]);

        return true;
    }

    public function marcarAtendida(): bool
    {
        if (in_array($this->estado, self::CERRADOS, true)) {
            return false;
        }

        $this->update(['estado' => 'ATENDIDA']);

        if ($this->cliente) {
            $this->cliente->increment('citas_totales');
            $this->cliente->update(['ultima_visita' => now()]);
        }

        return true;
    }

    // ------------------------------------------------------------------

    /** ¿Queda margen para cancelar con derecho a devolución? */
    public function enPlazoDeCancelacion(): bool
    {
        $horas = (int) config_empresa('cancelacion_horas_min', 24);

        $momento = \Illuminate\Support\Carbon::parse(
            $this->fecha->toDateString() . ' ' . $this->hora_ini
        );

        return $momento->isAfter(now()->addHours($horas));
    }

    public function estaAbierta(): bool
    {
        return ! in_array($this->estado, self::CERRADOS, true);
    }

    public function color(): string
    {
        return self::COLORES[$this->estado] ?? '#64748b';
    }

    public function duracionMinutos(): int
    {
        return Intervalo::aMinutos($this->hora_fin) - Intervalo::aMinutos($this->hora_ini);
    }

    public function resumenServicios(): string
    {
        return $this->lineas->pluck('nombre_servicio')->join(' + ');
    }

    /** Recalcula hora_fin e importe a partir de las líneas. */
    public function recalcular(): void
    {
        $lineas = $this->lineas()->get();

        if ($lineas->isEmpty()) {
            return;
        }

        $fin = $lineas->max(function ($linea) {
            return Intervalo::aMinutos($linea->hora_ini)
                 + $linea->duracion_min + $linea->tiempo_pausa_min + $linea->tiempo_final_min;
        });

        $this->update([
            'hora_ini'      => $lineas->min('hora_ini'),
            'hora_fin'      => Intervalo::aHora($fin),
            'importe_total' => $lineas->sum('precio'),
        ]);
    }
}
