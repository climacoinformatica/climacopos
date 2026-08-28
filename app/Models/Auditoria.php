<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Auditoria extends Model
{
    protected $table = 'log_auditoria';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha'   => 'datetime',
            'detalle' => 'array',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Registra una accion. Uso:
     *   Auditoria::registrar('login', detalle: ['via' => 'pin']);
     */
    public static function registrar(
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        ?array $detalle = null,
        ?int $usuarioId = null,
    ): void {
        self::create([
            'fecha'       => now(),
            'usuario_id'  => $usuarioId ?? session('salon.usuario_id'),
            'terminal_id' => session('salon.terminal_id'),
            'accion'      => $accion,
            'tabla'       => $tabla,
            'registro_id' => $registroId,
            'detalle'     => $detalle,
            'ip'          => Request::ip(),
        ]);
    }
}
