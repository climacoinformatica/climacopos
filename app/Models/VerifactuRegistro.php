<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de facturación VERI*FACTU.
 *
 * TABLA INMUTABLE. Una vez creado un registro no se edita ni se borra:
 * solo cambia su estado de envío. Un error se corrige con un registro
 * nuevo, igual que en contabilidad no se borra un apunte.
 */
class VerifactuRegistro extends Model
{
    protected $table = 'verifactu_registros';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha_expedicion' => 'date',
            'base'             => 'decimal:2',
            'cuota'            => 'decimal:2',
            'total'            => 'decimal:2',
            'tipo_impositivo'  => 'decimal:2',
            'enviado_en'       => 'datetime',
        ];
    }

    /**
     * Los campos que entran en la huella no se pueden modificar.
     * Si alguien lo intenta por descuido, salta aquí y no en una
     * inspección tres años después.
     */
    protected static function booted(): void
    {
        static::updating(function (self $registro) {
            $protegidos = [
                'tipo', 'nif_emisor', 'serie_numero', 'fecha_expedicion',
                'tipo_factura', 'base', 'cuota', 'total',
                'huella', 'huella_anterior', 'fecha_hora_huso',
            ];

            foreach ($protegidos as $campo) {
                if ($registro->isDirty($campo)) {
                    throw new \RuntimeException(
                        "El campo «{$campo}» de un registro VERI*FACTU no se puede modificar: "
                        . 'rompería la cadena de huellas. Emite un registro nuevo.'
                    );
                }
            }
        });

        static::deleting(function () {
            throw new \RuntimeException(
                'Los registros VERI*FACTU no se pueden borrar. '
                . 'Para anular una factura, emite un registro de anulación.'
            );
        });
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['PENDIENTE', 'ERROR_ENVIO'])
                     ->where('intentos', '<', 10);
    }

    public function scopeAceptados($query)
    {
        return $query->whereIn('estado', ['ACEPTADO', 'ACEPTADO_CON_ERRORES']);
    }

    /** El último registro de la cadena. Su huella encadena el siguiente. */
    public static function ultimo(): ?self
    {
        return static::orderByDesc('id')->first();
    }

    public function estaEnviado(): bool
    {
        return in_array($this->estado, ['ACEPTADO', 'ACEPTADO_CON_ERRORES'], true);
    }

    public const ETIQUETAS = [
        'PENDIENTE'            => 'Pendiente de enviar',
        'ENVIANDO'             => 'Enviando',
        'ACEPTADO'             => 'Aceptado',
        'ACEPTADO_CON_ERRORES' => 'Aceptado con avisos',
        'RECHAZADO'            => 'Rechazado',
        'ERROR_ENVIO'          => 'Error de envío',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->estado] ?? $this->estado;
    }

    /**
     * URL del QR que va impreso en el ticket.
     *
     * Permite a cualquier cliente comprobar en la sede de la AEAT que la
     * factura fue declarada. Es uno de los pilares del reglamento: el
     * consumidor puede verificar que el ticket existe.
     */
    public function urlQr(): string
    {
        $base = config('verifactu.pruebas', true)
            ? 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR'
            : 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

        return $base . '?' . http_build_query([
            'nif'    => $this->nif_emisor,
            'numserie' => $this->serie_numero,
            'fecha'  => $this->fecha_expedicion->format('d-m-Y'),
            'importe'=> number_format((float) $this->total, 2, '.', ''),
        ]);
    }
}
