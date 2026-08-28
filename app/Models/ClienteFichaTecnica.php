<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial técnico del cliente: fórmulas de color, tiempos de exposición,
 * incidencias. Es lo que permite repetir exactamente el mismo tono seis
 * semanas después, aunque atienda otra profesional.
 */
class ClienteFichaTecnica extends Model
{
    protected $table = 'cliente_ficha_tecnica';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
