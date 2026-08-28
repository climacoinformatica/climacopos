<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Cuenta del dominio central: quien contrata el servicio y paga.
 * No es lo mismo que App\Models\Usuario (empleado del salon, vive
 * dentro de la base de datos de cada empresa y entra con PIN).
 */
class Cuenta extends Authenticatable
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

    use Notifiable;

    protected $table = 'cuentas';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso'     => 'datetime',
            'password'          => 'hashed',
            'es_superadmin'     => 'boolean',
        ];
    }

    public function empresas()
    {
        return $this->belongsToMany(Empresa::class, 'cuenta_empresa')
                    ->withPivot('rol')
                    ->withTimestamps();
    }
}
