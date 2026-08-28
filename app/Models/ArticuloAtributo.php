<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticuloAtributo extends Model
{
    protected $table = 'articulo_atributos';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['visible_online' => 'boolean'];
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    /** Sugerencias habituales, para el desplegable del formulario. */
    public const SUGERENCIAS = [
        'Marca', 'Formato', 'Contenido', 'Tono', 'Acabado',
        'Tipo de cabello', 'Tipo de piel', 'Ingredientes',
        'Modo de empleo', 'Duración del efecto', 'Contraindicaciones',
    ];
}
