<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Avisos que alimentan la barra destellante del panel.
 *
 * Distincion importante: los avisos que exigen accion (una reserva online
 * sin confirmar) NO se apagan al leerlos. El destello sigue hasta que la
 * reserva se confirma o se rechaza. Si se apagaran con un clic, el
 * propietario podria silenciarlos sin querer y dejar clientes colgados.
 */
class Aviso extends Model
{
    protected $table = 'avisos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requiere_accion' => 'boolean',
            'resuelto'        => 'boolean',
            'leido'           => 'boolean',
            'leido_en'        => 'datetime',
        ];
    }

    public const ICONOS = [
        'RESERVA_NUEVA'     => '📅',
        'RESERVA_CANCELADA' => '✖',
        'STOCK_MINIMO'      => '📦',
        'ERROR_VERIFACTU'   => '⚠',
        'ERROR_AGENTE'      => '🖨',
    ];

    public function scopeActivos($query)
    {
        return $query->where('resuelto', false);
    }

    public function scopeQueDestellan($query)
    {
        return $query->where('resuelto', false)
                     ->where(fn ($q) => $q->where('requiere_accion', true)->orWhere('leido', false));
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'referencia_id');
    }

    public function icono(): string
    {
        return self::ICONOS[$this->tipo] ?? '•';
    }

    // ------------------------------------------------------------------

    public static function reservaNueva(Reserva $reserva): self
    {
        return self::create([
            'tipo'            => 'RESERVA_NUEVA',
            'referencia_id'   => $reserva->id,
            'titulo'          => 'Reserva online: ' . $reserva->cliente_nombre,
            'mensaje'         => $reserva->fecha->format('d/m/Y') . ' a las '
                                 . substr($reserva->hora_ini, 0, 5) . ' · '
                                 . $reserva->resumenServicios(),
            'requiere_accion' => true,
        ]);
    }

    public static function reservaCancelada(Reserva $reserva): self
    {
        return self::create([
            'tipo'          => 'RESERVA_CANCELADA',
            'referencia_id' => $reserva->id,
            'titulo'        => 'Cancelación: ' . $reserva->cliente_nombre,
            'mensaje'       => 'Cita del ' . $reserva->fecha->format('d/m/Y')
                               . ' a las ' . substr($reserva->hora_ini, 0, 5),
        ]);
    }

    /** Marca resueltos los avisos ligados a una reserva. */
    public static function resolverDeReserva(int $reservaId): void
    {
        self::where('referencia_id', $reservaId)
            ->whereIn('tipo', ['RESERVA_NUEVA'])
            ->update(['resuelto' => true]);
    }

    public function marcarLeido(?Usuario $usuario = null): void
    {
        $this->forceFill([
            'leido'     => true,
            'leido_por' => $usuario?->id,
            'leido_en'  => now(),
        ])->save();
    }

    /**
     * Huella del estado actual de los avisos. El panel sondea este valor
     * y solo pide el detalle cuando cambia, para no cargar la base de datos.
     */
    public static function huella(): string
    {
        $datos = self::queDestellan()
            ->orderBy('id')
            ->pluck('updated_at', 'id')
            ->toJson();

        return substr(md5($datos), 0, 12);
    }
}
