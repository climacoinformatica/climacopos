<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as DomainBase;

class Dominio extends DomainBase
{
    protected $table = 'dominios';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'es_principal'  => 'boolean',
            'es_propio'     => 'boolean',
            'verificado_en' => 'datetime',
        ];
    }
}
