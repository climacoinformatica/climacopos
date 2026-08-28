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
}
