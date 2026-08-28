<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteFoto extends Model
{
    protected $table = 'cliente_fotos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function url(): string
    {
        return tenant_asset($this->ruta);
    }

    public function urlMini(): string
    {
        return tenant_asset($this->ruta_mini ?: $this->ruta);
    }
}
