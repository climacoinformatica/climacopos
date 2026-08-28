<?php

namespace App\Models;

use App\Models\Scopes\ExcluirFormacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $guarded = [];

    /** Serie de los documentos de practicas. No consume numeracion fiscal. */
    public const SERIE_FORMACION = 'FOR';
    public const SERIE_NORMAL    = 'A';
    public const SERIE_RECTIFICATIVA = 'R';

    protected function casts(): array
    {
        return [
            'fecha'         => 'datetime',
            'base'          => 'decimal:2',
            'impuesto'      => 'decimal:2',
            'descuento'     => 'decimal:2',
            'total'         => 'decimal:2',
            'es_formacion'  => 'boolean',
            'es_invitacion' => 'boolean',
            'anulado_en'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Por defecto, los documentos de formacion NO existen
        static::addGlobalScope(new ExcluirFormacion());

        static::creating(function (self $ticket) {
            $ticket->uuid ??= (string) Str::uuid();
        });
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Reales + formacion. */
    public function scopeConFormacion(Builder $q): Builder
    {
        return $q->withoutGlobalScope(ExcluirFormacion::class);
    }

    /** Solo practicas. */
    public function scopeSoloFormacion(Builder $q): Builder
    {
        return $q->withoutGlobalScope(ExcluirFormacion::class)->where('es_formacion', true);
    }

    public function scopeCobrados(Builder $q): Builder
    {
        return $q->where('estado', 'COBRADO');
    }

    public function scopeSinCerrar(Builder $q): Builder
    {
        return $q->whereNull('cierre_id')->where('estado', 'COBRADO');
    }

    public function scopeDelDia(Builder $q, $fecha): Builder
    {
        return $q->whereDate('fecha', $fecha);
    }

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------

    public function lineas()
    {
        return $this->hasMany(TicketLinea::class)->orderBy('orden');
    }

    public function cobros()
    {
        return $this->hasMany(TicketCobro::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    public function cierre()
    {
        return $this->belongsTo(CierreJornada::class, 'cierre_id');
    }

    // ------------------------------------------------------------------
    // Numeracion
    // ------------------------------------------------------------------

    /**
     * Siguiente numero de una serie, con bloqueo de fila.
     *
     * Nunca usar MAX(numero)+1 suelto: con dos terminales cobrando a la
     * vez saldrian numeros duplicados, y eso es un problema fiscal, no
     * un detalle estetico.
     */
    public static function siguienteNumero(string $serie): int
    {
        $ultimo = DB::table('tickets')
            ->where('serie', $serie)
            ->lockForUpdate()
            ->max('numero');

        return (int) $ultimo + 1;
    }

    public function referencia(): string
    {
        return $this->serie . '-' . str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Importes
    // ------------------------------------------------------------------

    public function totalCobrado(): float
    {
        return (float) $this->cobros()->sum('importe');
    }

    public function pendiente(): float
    {
        return round((float) $this->total - $this->totalCobrado(), 2);
    }

    public function estaPagado(): bool
    {
        return $this->pendiente() <= 0.001;
    }

    /** Recalcula totales desde las lineas. */
    public function recalcular(): void
    {
        $lineas = $this->lineas()->get();

        $this->update([
            'base'     => round($lineas->sum('base'), 2),
            'impuesto' => round($lineas->sum('impuesto'), 2),
            'total'    => round($lineas->sum('importe'), 2),
        ]);
    }

    public function cobroEfectivo(): float
    {
        return (float) $this->cobros()->where('medio', 'EFECTIVO')->sum('importe');
    }

    public function medios(): string
    {
        return $this->cobros->pluck('medio')->unique()
            ->map(fn ($m) => ucfirst(strtolower($m)))->join(' + ');
    }

    /**
     * Anulable solo hasta el cierre.
     *
     * Despues hay que rectificar: anular un ticket ya cerrado descuadraria
     * un arqueo que se dio por bueno.
     */
    public function esAnulable(): bool
    {
        return $this->estado !== 'ANULADO'
            && is_null($this->cierre_id)
            && $this->tipo_documento !== 'RECTIFICATIVA';
    }

    /** Devolvible cuando ya no se puede anular. */
    public function esDevolvible(): bool
    {
        return $this->estado === 'COBRADO'
            && $this->tipo_documento === 'NORMAL'
            && ! $this->es_formacion;
    }

    public function esRectificativa(): bool
    {
        return $this->tipo_documento === 'RECTIFICATIVA';
    }

    public function rectificaA()
    {
        return $this->belongsTo(self::class, 'rectifica_ticket_id');
    }

    public function rectificativas()
    {
        return $this->hasMany(self::class, 'rectifica_ticket_id');
    }

    /** Lo devuelto de este ticket, en positivo. */
    public function importeDevuelto(): float
    {
        return abs((float) $this->rectificativas()->where('estado', 'COBRADO')->sum('total'));
    }

    public function tieneDevoluciones(): bool
    {
        return $this->importeDevuelto() > 0.001;
    }

    /** Lo que realmente se quedo el salon. */
    public function importeNeto(): float
    {
        return round((float) $this->total - $this->importeDevuelto(), 2);
    }
}
