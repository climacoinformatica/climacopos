<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Cliente extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'fecha_alta'           => 'datetime',
            'fecha_nac'            => 'date',
            'fecha_consentimiento' => 'datetime',
            'ultima_visita'        => 'datetime',
            'acepta_rgpd'          => 'boolean',
            'acepta_marketing'     => 'boolean',
            'bloqueado'            => 'boolean',
            'saldo_monedero'       => 'decimal:2',
            'password'             => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $cliente) {
            $cliente->uuid ??= (string) Str::uuid();
            $cliente->codigo ??= self::siguienteCodigo();
        });
    }

    public static function siguienteCodigo(): string
    {
        $ultimo = static::withTrashed()->max('id') ?? 0;

        return 'C' . str_pad((string) ($ultimo + 1), 5, '0', STR_PAD_LEFT);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class)->orderByDesc('fecha');
    }

    public function bonos()
    {
        return $this->hasMany(Bono::class)->orderByDesc('id');
    }

    public function bonosActivos()
    {
        return $this->hasMany(Bono::class)->utilizables();
    }

    public function movimientosMonedero()
    {
        return $this->hasMany(MonederoMovimiento::class)->orderByDesc('fecha');
    }

    public function vales()
    {
        return $this->hasMany(Vale::class);
    }

    public function tieneSaldo(): bool
    {
        return (float) $this->saldo_monedero > 0.001;
    }

    /**
     * Todo lo que este cliente tiene ya pagado por adelantado: monedero,
     * bonos y vales. Es lo que conviene ver de un vistazo al abrir su
     * ficha, para poder ofrecerselo antes de cobrarle otra vez.
     */
    public function saldoTotalDisponible(): float
    {
        $bonos = $this->bonosActivos()->get()
            ->sum(fn (Bono $bono) => $bono->valorRestante());

        $vales = (float) $this->vales()->utilizables()->sum('importe_restante');

        return round((float) $this->saldo_monedero + $bonos + $vales, 2);
    }

    public function fotos()
    {
        return $this->hasMany(ClienteFoto::class)->orderByDesc('fecha');
    }

    public function fichaTecnica()
    {
        return $this->hasMany(ClienteFichaTecnica::class)->orderByDesc('fecha');
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellidos);
    }

    public function iniciales(): string
    {
        return Str::upper(Str::substr($this->nombre, 0, 1) . Str::substr($this->apellidos ?? '', 0, 1));
    }

    /**
     * Un cliente con varios plantones debe pagar por adelantado.
     * Es la única forma de proteger la agenda sin bloquear a nadie.
     */
    public function debePagarPorAdelantado(): bool
    {
        return $this->no_shows >= (int) config_empresa('no_shows_para_exigir_pago', 2);
    }

    public function scopeBuscar($query, string $texto)
    {
        $patron = '%' . $texto . '%';

        return $query->where(fn ($q) => $q
            ->where('nombre', 'like', $patron)
            ->orWhere('apellidos', 'like', $patron)
            ->orWhere('telefono', 'like', $patron)
            ->orWhere('email', 'like', $patron)
            ->orWhere('codigo', 'like', $patron));
    }

    /** Localiza por teléfono, que es como se identifica en el portal. */
    public static function porTelefono(?string $telefono): ?self
    {
        if (blank($telefono)) {
            return null;
        }

        $limpio = preg_replace('/[^0-9]/', '', $telefono);

        return static::whereRaw("REPLACE(REPLACE(REPLACE(telefono,' ',''),'-',''),'+','') LIKE ?", ["%{$limpio}"])
                     ->first();
    }
}
