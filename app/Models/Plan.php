<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
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

    protected $table = 'planes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'precio_mes'         => 'decimal:2',
            'precio_ano'         => 'decimal:2',
            'reservas_online'    => 'boolean',
            'pagos_online'       => 'boolean',
            'verifactu'          => 'boolean',
            'dominio_propio'     => 'boolean',
            'informes_avanzados' => 'boolean',
            'activo'             => 'boolean',
        ];
    }

    public function empresas()
    {
        return $this->hasMany(Empresa::class);
    }

    /** Cada plan pertenece a un producto: los tres tienen los suyos. */
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Solo los planes del producto que se usa en la nube.
     *
     * Los salones son de CLIMACO POS Beauty, que es el unico SaaS. Los
     * planes de Restaurant y Gym son para programas que se instalan, y
     * su cobro ira ligado a una licencia, no a un panel.
     *
     * Se filtra por modalidad y no por slug: si algun dia hay otro
     * producto en la nube, seguira funcionando sin tocar nada.
     */
    public function scopeDeLaNube($query)
    {
        return $query->whereHas('producto', fn ($q) => $q->where('modalidad', 'SAAS'));
    }

    /** Los planes de un producto concreto. */
    public function scopeDeProducto($query, $producto)
    {
        return $query->where('producto_id',
            $producto instanceof Producto ? $producto->id : $producto);
    }

    /**
     * Nombre con el producto delante.
     *
     * En la pantalla del salon sobra —ya sabe que programa usa— pero en
     * una factura o en el panel de Stripe, «Basico» a secas no dice de
     * que producto es.
     */
    public function nombreCompleto(): string
    {
        if (! $this->producto) {
            return $this->nombre;
        }

        return $this->nombre . ' · ' . $this->producto->nombre;
    }

    /** Lo que se le dice al cliente sobre el soporte incluido. */
    public function soporteLegible(): string
    {
        if (filled($this->soporte_texto)) {
            return $this->soporte_texto;
        }

        return match ($this->soporte) {
            'EMAIL'    => 'Soporte por correo electrónico',
            'COMPLETO' => 'Soporte por teléfono, correo y remoto',
            default    => 'Sin soporte incluido',
        };
    }

    /** Ahorro al pagar el año por adelantado. */
    public function ahorroAnual(): float
    {
        return round(((float) $this->precio_mes * 12) - (float) $this->precio_ano, 2);
    }
}
