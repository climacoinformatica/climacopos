<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Articulo extends \Illuminate\Database\Eloquent\Model
{
    use SoftDeletes;

    protected $table = 'articulos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'precio'                 => 'decimal:2',
            'impuesto_pct'           => 'decimal:2',
            'coste'                  => 'decimal:2',
            'stock'                  => 'decimal:3',
            'stock_min'              => 'decimal:3',
            'fianza_importe'         => 'decimal:2',
            'fianza_pct'             => 'decimal:2',
            'control_stock'          => 'boolean',
            'permite_reserva_online' => 'boolean',
            'requiere_confirmacion'  => 'boolean',
            'activo'                 => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $articulo) {
            $articulo->uuid ??= (string) Str::uuid();
        });
    }

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------

    /**
     * Plantilla de bono que vende este articulo, si lo es.
     *
     * Un articulo con bono_plantilla_id no es un servicio: es la venta
     * del bono. Al cobrarlo se emite el bono a nombre del cliente.
     */
    public function bonoPlantilla()
    {
        return $this->belongsTo(BonoPlantilla::class, 'bono_plantilla_id');
    }

    public function esVentaDeBono(): bool
    {
        return $this->bono_plantilla_id !== null;
    }

    public function familia()
    {
        return $this->belongsTo(Familia::class);
    }

    public function fotos()
    {
        return $this->hasMany(ArticuloFoto::class)->orderBy('orden');
    }

    public function fotoPrincipal()
    {
        return $this->hasOne(ArticuloFoto::class)->where('principal', true);
    }

    public function atributos()
    {
        return $this->hasMany(ArticuloAtributo::class)->orderBy('orden');
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class);
    }

    /** Profesionales que realizan este servicio. Vacío = cualquiera. */
    public function profesionales()
    {
        return $this->belongsToMany(Usuario::class, 'articulo_profesional', 'articulo_id', 'usuario_id')
                    ->withPivot(['precio', 'duracion_min']);
    }

    public function componentes()
    {
        return $this->belongsToMany(self::class, 'articulo_componentes', 'pack_id', 'articulo_id')
                    ->withPivot(['cantidad', 'orden']);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeServicios($query)
    {
        return $query->whereIn('tipo', ['SERVICIO', 'BONO', 'PACK']);
    }

    public function scopeProductos($query)
    {
        return $query->where('tipo', 'PRODUCTO');
    }

    public function scopeReservablesOnline($query)
    {
        return $query->where('activo', true)
                     ->where('permite_reserva_online', true)
                     ->whereIn('tipo', ['SERVICIO', 'PACK']);
    }

    public function scopeBajoMinimo($query)
    {
        return $query->where('control_stock', true)
                     ->whereColumn('stock', '<=', 'stock_min');
    }

    // ------------------------------------------------------------------
    // Precios
    //
    // El precio guardado SIEMPRE lleva el impuesto incluido: es como se
    // anuncia al cliente y como se teclea en el TPV. La base imponible se
    // calcula hacia atrás, nunca al revés.
    // ------------------------------------------------------------------

    public function baseImponible(?float $precio = null): float
    {
        $precio ??= (float) $this->precio;

        return round($precio / (1 + ((float) $this->impuesto_pct / 100)), 4);
    }

    public function cuotaImpuesto(?float $precio = null): float
    {
        $precio ??= (float) $this->precio;

        return round($precio - $this->baseImponible($precio), 4);
    }

    public function margen(): ?float
    {
        if (is_null($this->coste) || (float) $this->coste == 0.0) {
            return null;
        }

        $base = $this->baseImponible();

        return round((($base - (float) $this->coste) / $base) * 100, 2);
    }

    /** Precio para un profesional concreto, si tiene tarifa propia. */
    public function precioPara(?Usuario $profesional): float
    {
        if (! $profesional) {
            return (float) $this->precio;
        }

        $pivot = $this->profesionales->firstWhere('id', $profesional->id)?->pivot;

        return (float) ($pivot?->precio ?? $this->precio);
    }

    // ------------------------------------------------------------------
    // Duración
    // ------------------------------------------------------------------

    public function duracionPara(?Usuario $profesional = null): int
    {
        if ($profesional) {
            $pivot = $this->profesionales->firstWhere('id', $profesional->id)?->pivot;

            if ($pivot?->duracion_min) {
                return (int) $pivot->duracion_min;
            }
        }

        return (int) $this->duracion_min;
    }

    /**
     * Minutos totales que ocupa la cita en la agenda, incluida la pausa.
     * El profesional solo está ocupado en duracion_min + tiempo_final_min;
     * durante la pausa puede atender a otro cliente.
     */
    public function duracionTotal(?Usuario $profesional = null): int
    {
        return $this->duracionPara($profesional)
             + (int) $this->tiempo_pausa_min
             + (int) $this->tiempo_final_min;
    }

    public function tienePausa(): bool
    {
        return (int) $this->tiempo_pausa_min > 0;
    }

    // ------------------------------------------------------------------
    // Reserva online
    // ------------------------------------------------------------------

    public function importeFianza(): float
    {
        return match ($this->politica_pago) {
            'TOTAL'  => (float) $this->precio,
            'FIANZA' => $this->fianza_importe !== null
                        ? (float) $this->fianza_importe
                        : round((float) $this->precio * ((float) $this->fianza_pct / 100), 2),
            default  => 0.0,
        };
    }

    public function esServicio(): bool
    {
        return in_array($this->tipo, ['SERVICIO', 'PACK'], true);
    }

    public function urlFoto(): ?string
    {
        $foto = $this->relationLoaded('fotos')
            ? $this->fotos->firstWhere('principal', true) ?? $this->fotos->first()
            : $this->fotos()->orderByDesc('principal')->first();

        return $foto?->url();
    }
}
