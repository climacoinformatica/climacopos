<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Excluye los documentos de formacion de TODAS las consultas.
 *
 * Esto es deliberadamente un global scope y no un `where` que haya que
 * recordar poner: en el POS de hosteleria el fallo fue justamente ese,
 * olvidar el filtro en una consulta suelta y colar tickets de practicas
 * en un informe real.
 *
 * Para verlos hay que pedirlo explicitamente:
 *
 *     Ticket::conFormacion()->get();     // reales + formacion
 *     Ticket::soloFormacion()->get();    // solo practicas
 */
class ExcluirFormacion implements Scope
{
    public function apply(Builder $constructor, Model $modelo): void
    {
        $constructor->where($modelo->getTable() . '.es_formacion', false);
    }
}
