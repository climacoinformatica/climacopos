<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Equipo autorizado a usar el panel del salon.
 *
 * Opcion C: el equipo se vincula UNA vez con credenciales completas y a
 * partir de ahi los empleados entran solo con PIN. El token se guarda en
 * una cookie de larga duracion y aqui solo se almacena su hash.
 */
class TerminalVinculo extends Model
{
    protected $table = 'terminal_vinculos';

    protected $guarded = [];

    public const COOKIE = 'climacopos_terminal';
    public const DIAS_VALIDEZ = 365;

    protected function casts(): array
    {
        return [
            'ultima_conexion' => 'datetime',
            'revocado_en'     => 'datetime',
        ];
    }

    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }

    /**
     * Crea el vinculo y devuelve [modelo, token en claro].
     * El token en claro solo existe en este momento; despues es irrecuperable.
     */
    public static function emitir(Terminal $terminal, ?Usuario $usuario, ?string $dispositivo = null): array
    {
        $tokenPlano = Str::random(60);

        $vinculo = self::create([
            'terminal_id'     => $terminal->id,
            'token'           => hash('sha256', $tokenPlano),
            'dispositivo'     => Str::limit($dispositivo ?? 'Equipo sin identificar', 115),
            'vinculado_por'   => $usuario?->id,
            'ultima_conexion' => now(),
        ]);

        return [$vinculo, $tokenPlano];
    }

    public static function porToken(?string $tokenPlano): ?self
    {
        if (blank($tokenPlano)) {
            return null;
        }

        return self::whereNull('revocado_en')
            ->where('token', hash('sha256', $tokenPlano))
            ->first();
    }

    public function registrarUso(?string $ip = null): void
    {
        $this->forceFill([
            'ultima_conexion' => now(),
            'ultima_ip'       => $ip,
        ])->saveQuietly();
    }

    public function revocar(): void
    {
        $this->forceFill(['revocado_en' => now()])->save();
    }
}
