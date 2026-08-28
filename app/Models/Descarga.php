<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Descarga extends Model
{
    use CentralConnection;

    protected $table = 'descargas';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function version()
    {
        return $this->belongsTo(ProductoVersion::class, 'version_id');
    }
}
