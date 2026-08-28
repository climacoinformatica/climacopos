<?php

namespace App\Support;

use App\Models\Empresa;
use Illuminate\Support\Str;

class Slug
{
    /**
     * Subdominios que no puede ocupar ninguna empresa.
     * Añadir aqui cualquier subdominio que uses o pienses usar:
     * si un salon se queda con 'soporte', lo pierdes para siempre.
     */
    public const RESERVADOS = [
        'www', 'app', 'api', 'admin', 'panel', 'cdn', 'static', 'assets',
        'mail', 'smtp', 'imap', 'pop', 'webmail', 'ns1', 'ns2', 'mx',
        'blog', 'docs', 'ayuda', 'soporte', 'status', 'estado',
        'cuenta', 'cuentas', 'login', 'registro', 'signup', 'billing',
        'pos', 'tpv', 'agente', 'agent', 'webhook', 'webhooks',
        'myclimaco', 'climaco', 'climacopos', 'informatica',
        'test', 'dev', 'staging', 'demo', 'beta', 'local',
    ];

    public static function normalizar(string $valor): string
    {
        return Str::of($valor)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9-]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->limit(40, '')
            ->value();
    }

    public static function esValido(string $slug): bool
    {
        // Minimo 3 caracteres, empieza y acaba en alfanumerico.
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $slug);
    }

    public static function estaReservado(string $slug): bool
    {
        return in_array($slug, self::RESERVADOS, true);
    }

    public static function estaLibre(string $slug): bool
    {
        return self::esValido($slug)
            && ! self::estaReservado($slug)
            && ! Empresa::withTrashed()->where('slug', $slug)->exists();
    }

    /** Devuelve el motivo del rechazo, o null si el slug es utilizable. */
    public static function motivoRechazo(string $slug): ?string
    {
        if (! self::esValido($slug)) {
            return 'Solo minusculas, numeros y guiones. Entre 3 y 40 caracteres.';
        }

        if (self::estaReservado($slug)) {
            return 'Ese nombre esta reservado por la plataforma.';
        }

        if (Empresa::withTrashed()->where('slug', $slug)->exists()) {
            return 'Ya hay otro negocio con ese nombre.';
        }

        return null;
    }
}
