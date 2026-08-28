<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class ProductoVersion extends Model
{
    use CentralConnection;

    protected $table = 'producto_versiones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'publicada_el' => 'date',
            'es_actual'    => 'boolean',
            'publica'      => 'boolean',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function descargasRegistradas()
    {
        return $this->hasMany(Descarga::class, 'version_id');
    }

    /** Tamaño en unidades legibles. */
    public function tamanoLegible(): string
    {
        $bytes = (int) $this->tamano;

        if ($bytes <= 0) {
            return '—';
        }

        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i > 1 ? 1 : 0, ',', '.') . ' ' . $unidades[$i];
    }

    /**
     * Marca esta versión como la actual y desmarca las demás.
     *
     * Se hace aquí y no a mano porque dos versiones marcadas como
     * actuales dejarían la descarga a merced del orden de la consulta.
     */
    public function marcarActual(): void
    {
        static::where('producto_id', $this->producto_id)
            ->where('id', '!=', $this->id)
            ->update(['es_actual' => false]);

        $this->update(['es_actual' => true]);
    }
}
