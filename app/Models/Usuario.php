<?php

namespace App\Models;

use App\Support\Permisos;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Empleado del salon. Vive dentro de la base de datos de cada empresa.
 * No confundir con App\Models\Cuenta, que es quien contrata y paga
 * desde el dominio central.
 */
class Usuario extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'usuarios';

    protected $guarded = [];

    protected $hidden = ['password', 'pin', 'remember_token'];

    /**
     * Valores por defecto en memoria.
     *
     * Sin esto, un Usuario::create() que no pase 'en_formacion' deja el
     * atributo en NULL dentro del objeto: la base de datos aplica su
     * default, pero el modelo recien creado no lo sabe. Ese NULL se
     * propagaba despues a 'es_formacion' del ticket y reventaba el
     * INSERT. Es un fallo silencioso y dificil de rastrear.
     */
    protected $attributes = [
        'es_profesional' => false,
        'en_formacion'   => false,
        'estado'         => 'ACTIVO',
        'color_agenda'   => '#6366f1',
        'comision_pct'   => 0,
        'intentos_pin'   => 0,
        'orden'          => 0,
    ];

    /** Minutos que dura la reautenticacion por contrasena (opcion C). */
    public const MINUTOS_REAUTENTICACION = 15;

    /** Intentos de PIN antes de bloquear. */
    public const MAX_INTENTOS_PIN = 5;
    public const MINUTOS_BLOQUEO_PIN = 5;

    protected function casts(): array
    {
        return [
            'password'            => 'hashed',
            'pin'                 => 'hashed',
            'es_profesional'      => 'boolean',
            'en_formacion'        => 'boolean',
            'ultimo_acceso'       => 'datetime',
            'pin_bloqueado_hasta' => 'datetime',
            'comision_pct'        => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $usuario) {
            $usuario->uuid ??= (string) Str::uuid();
            $usuario->alias ??= Str::before($usuario->nombre, ' ');
        });
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class);
    }

    public function horarios()
    {
        return $this->hasMany(UsuarioHorario::class);
    }

    public function excepciones()
    {
        return $this->hasMany(UsuarioExcepcion::class);
    }

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    public function tienePermiso(string $clave): bool
    {
        if ($this->estado !== 'ACTIVO') {
            return false;
        }

        return $this->perfil?->tienePermiso($clave) ?? false;
    }

    public function tieneAlgunPermiso(array $claves): bool
    {
        foreach ($claves as $clave) {
            if ($this->tienePermiso($clave)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // PIN
    // ------------------------------------------------------------------

    public function pinBloqueado(): bool
    {
        return $this->pin_bloqueado_hasta?->isFuture() ?? false;
    }

    public function comprobarPin(string $pin): bool
    {
        if ($this->pinBloqueado() || blank($this->pin)) {
            return false;
        }

        if (! Hash::check($pin, $this->pin)) {
            $this->registrarFalloPin();

            return false;
        }

        $this->forceFill([
            'intentos_pin'        => 0,
            'pin_bloqueado_hasta' => null,
            'ultimo_acceso'       => now(),
        ])->saveQuietly();

        return true;
    }

    protected function registrarFalloPin(): void
    {
        $this->intentos_pin++;

        if ($this->intentos_pin >= self::MAX_INTENTOS_PIN) {
            $this->pin_bloqueado_hasta = now()->addMinutes(self::MINUTOS_BLOQUEO_PIN);
            $this->intentos_pin = 0;
        }

        $this->saveQuietly();
    }

    public function comprobarPassword(string $password): bool
    {
        return filled($this->password) && Hash::check($password, $this->password);
    }

    // ------------------------------------------------------------------
    // Formacion
    // ------------------------------------------------------------------

    /**
     * Medios de pago que este usuario puede utilizar.
     * Un empleado en formacion SOLO puede cobrar en efectivo, y sus
     * documentos quedan fuera del cierre de jornada.
     */
    /** Nunca comprobar el flag directamente: puede venir null. */
    public function estaEnFormacion(): bool
    {
        return (bool) $this->en_formacion;
    }

    public function mediosPagoPermitidos(): array
    {
        if ($this->estaEnFormacion()) {
            return ['EFECTIVO'];
        }

        return [
            'EFECTIVO', 'TARJETA', 'BIZUM', 'TRANSFERENCIA',
            'ANTICIPO', 'MONEDERO', 'BONO', 'VALE',
        ];
    }

    public function puedeCobrarCon(string $medio): bool
    {
        return in_array(strtoupper($medio), $this->mediosPagoPermitidos(), true);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeProfesionales($query)
    {
        return $query->where('es_profesional', true);
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim($this->nombre));

        return Str::upper(
            Str::substr($partes[0] ?? '', 0, 1) . Str::substr($partes[1] ?? '', 0, 1)
        );
    }

    public function esPropietario(): bool
    {
        return $this->perfil?->clave === 'propietario';
    }
}
