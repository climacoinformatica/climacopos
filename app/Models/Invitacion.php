<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitacion extends Model
{
    protected $table = 'invitaciones';

    protected $guarded = [];

    public const DIAS_VALIDEZ = 7;

    protected function casts(): array
    {
        return [
            'caduca_en'      => 'datetime',
            'aceptada_en'    => 'datetime',
            'es_profesional' => 'boolean',
            'en_formacion'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitacion) {
            $invitacion->token ??= Str::random(64);
            $invitacion->caduca_en ??= now()->addDays(self::DIAS_VALIDEZ);
        });
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function estaVigente(): bool
    {
        return is_null($this->aceptada_en) && $this->caduca_en->isFuture();
    }

    public function url(): string
    {
        return tenant()->urlPortal() . '/panel/invitacion/' . $this->token;
    }
}
