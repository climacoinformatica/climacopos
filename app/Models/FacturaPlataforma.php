<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Factura que la plataforma emite a un salón por su cuota.
 * No confundir con los tickets del salón a sus clientes.
 */
class FacturaPlataforma extends Model
{
    /**
     * Este modelo vive en la base CENTRAL.
     *
     * Dentro del contexto de una empresa, la conexion por defecto de
     * Eloquent es la del salon, asi que sin esto la consulta buscaria la
     * tabla en climacopos_emp_N y no existe alli. El trait CentralConnection
     * de stancl/tenancy fuerza la conexion central siempre.
     */
    use CentralConnection;

    protected $table = 'facturas_plataforma';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'importe'       => 'decimal:2',
            'impuesto'      => 'decimal:2',
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'pagada_en'     => 'datetime',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeImpagadas($query)
    {
        return $query->whereIn('estado', ['PENDIENTE', 'IMPAGADA']);
    }

    public const ETIQUETAS = [
        'BORRADOR'  => 'Borrador',
        'PENDIENTE' => 'Pendiente de pago',
        'PAGADA'    => 'Pagada',
        'IMPAGADA'  => 'Impagada',
        'ANULADA'   => 'Anulada',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->estado] ?? $this->estado;
    }

    public function periodo(): string
    {
        if (! $this->periodo_desde || ! $this->periodo_hasta) {
            return '—';
        }

        return $this->periodo_desde->format('d/m/Y') . ' – ' . $this->periodo_hasta->format('d/m/Y');
    }
}
