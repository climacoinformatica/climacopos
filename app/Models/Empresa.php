<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as TenantBase;

class Empresa extends TenantBase implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use SoftDeletes;

    protected $table = 'empresas';

    /**
     * CORRECCION: el modelo Tenant del paquete asume clave primaria de tipo
     * texto (UUID) y no autoincremental. Nuestra tabla 'empresas' usa un
     * bigint autoincremental, asi que hay que revertir las tres propiedades.
     * Debe ir acompanado de 'id_generator' => null en config/tenancy.php
     */
    public $incrementing = true;
    protected $keyType = 'int';
    protected $primaryKey = 'id';

    /**
     * Prefijo del nombre de base de datos de cada empresa.
     * Resultado: climacopos_emp_1, climacopos_emp_2...
     * Se usa el id numerico y NO el slug, porque el slug puede cambiar
     * y el nombre de la base de datos no debe cambiar jamas.
     */
    public const PREFIJO_BD = 'climacopos_emp_';

    /**
     * Columnas reales de la tabla.
     * Cualquier atributo que NO este en esta lista lo guarda stancl/tenancy
     * automaticamente dentro de la columna JSON 'data'.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'uuid',
            'slug',
            'nombre_comercial',
            'razon_social',
            'nif',
            'email',
            'telefono',
            'direccion',
            'cp',
            'municipio',
            'provincia',
            'pais',
            'zona_horaria',
            'moneda',
            'regimen_fiscal',
            'logo',
            'color_marca',
            'plan_id',
            'estado',
            'prueba_hasta',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_connect_id',
            'onboarding_completado',
            'tenancy_db_name',
            'suspendida_en',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    protected function casts(): array
    {
        /**
         * Todo campo de fecha necesita su cast.
         *
         * Sin el, Eloquent devuelve la cadena de texto de la base de datos
         * y cualquier ->isFuture() revienta con «Call to a member function
         * on string». Es un fallo silencioso que no aparece hasta que se
         * usa el campo.
         */
        return [
            'prueba_hasta'           => 'date',
            'onboarding_completado'  => 'boolean',
            'suspendida_en'          => 'datetime',

            // --- Suscripcion y morosidad (Fase 9)
            'impagos'                => 'integer',
            'primer_impago_en'       => 'datetime',
            'suspension_efectiva_en' => 'datetime',
            'aviso_borrado_en'       => 'datetime',
            'borrar_a_partir_de'     => 'datetime',
            'suscripcion_hasta'      => 'datetime',
            'cancela_al_terminar'    => 'boolean',

            // --- Stripe Connect (Fase 8)
            'stripe_cobros_activos'  => 'boolean',
            'stripe_verificado_en'   => 'datetime',
            'comision_plataforma_pct'=> 'decimal:2',

            // --- VERI*FACTU (Fase 10)
            'verifactu_activo'       => 'boolean',
            'certificado_caduca'     => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $empresa) {
            $empresa->uuid ??= (string) Str::uuid();
            $empresa->prueba_hasta ??= now()
                ->addDays((int) config('climacopos.prueba_dias', 14))
                ->toDateString();
        });

        // El id no existe hasta despues del INSERT, asi que el nombre de la
        // base de datos se fija justo despues de crear la fila.
        // stancl/tenancy leera 'tenancy_db_name' al conectarse.
        static::created(function (self $empresa) {
            if (blank($empresa->tenancy_db_name)) {
                $empresa->tenancy_db_name = self::PREFIJO_BD . $empresa->id;
                $empresa->saveQuietly();
            }
        });
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function cuentas()
    {
        return $this->belongsToMany(Cuenta::class, 'cuenta_empresa')
                    ->withPivot('rol')
                    ->withTimestamps();
    }

    /** URL publica del portal de reservas. */
    public function urlPortal(): string
    {
        $dominio = $this->domains()->where('es_principal', true)->value('domain')
                   ?? $this->slug . '.' . config('climacopos.dominio_base');

        return (app()->environment('local') ? 'http://' : 'https://') . $dominio;
    }

    public function estaOperativa(): bool
    {
        return in_array($this->estado, ['PRUEBA', 'ACTIVA', 'MOROSA'], true);
    }

    /** Dias transcurridos desde la baja logica. Null si no esta de baja. */
    public function diasDesdeBaja(): ?int
    {
        return $this->deleted_at?->diffInDays(now());
    }
}
