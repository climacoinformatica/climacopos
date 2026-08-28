<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stancl\Tenancy\Jobs\DeleteDatabase;

/**
 * Alta automática de un salón.
 *
 * Es la operación más delicada del sistema: crea una base de datos
 * nueva, la migra, la siembra y da de alta al propietario. Cinco pasos
 * que pueden fallar por separado.
 *
 * EL PROBLEMA DE FONDO
 *
 * `CREATE DATABASE` no participa en transacciones. Si el alta falla a
 * mitad, la base ya está creada y una transacción de Laravel no la
 * deshace. Por eso aquí se limpia a mano: si algo revienta después de
 * crear el tenant, se borra el tenant y su base antes de propagar el
 * error. Un salón a medio crear es peor que ninguno, porque el cliente
 * ve su subdominio ocupado y no puede volver a intentarlo.
 */
class GestorAltas
{
    /**
     * Subdominios que nadie puede pedir.
     *
     * Unos son nuestros y otros son convenciones que rompen cosas: `www`
     * y `mail` los usan los clientes de correo, `admin` es el panel de
     * superadministración, y `api` la reservamos para lo que venga.
     */
    public const RESERVADOS = [
        'www', 'admin', 'api', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns',
        'ns1', 'ns2', 'cpanel', 'webmail', 'blog', 'shop', 'tienda', 'app',
        'panel', 'soporte', 'ayuda', 'help', 'docs', 'status', 'cdn',
        'static', 'assets', 'img', 'test', 'dev', 'staging', 'demo',
        'climaco', 'climacopos', 'pos', 'beauty', 'gym', 'restaurant',
        'facturacion', 'pagos', 'stripe', 'aeat', 'verifactu',
    ];

    // ------------------------------------------------------------------
    // Comprobación del subdominio
    // ------------------------------------------------------------------

    /**
     * ¿Se puede usar este subdominio?
     *
     * @return array{ok: bool, motivo: string|null, sugerencia: string|null}
     */
    public function comprobarSlug(string $slug): array
    {
        $slug = strtolower(trim($slug));

        if (mb_strlen($slug) < 3) {
            return $this->no('Hacen falta al menos tres letras.');
        }

        if (mb_strlen($slug) > 40) {
            return $this->no('Como mucho cuarenta caracteres.');
        }

        /**
         * Solo letras, números y guiones, sin empezar ni acabar en guión.
         *
         * No es un capricho: así lo exige el estándar de nombres de
         * dominio. Un subdominio con un punto o una eñe no resuelve.
         */
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
            return $this->no(
                'Solo letras sin tildes, números y guiones. No puede empezar '
                . 'ni terminar con guión.'
            );
        }

        if (in_array($slug, self::RESERVADOS, true)) {
            return $this->no('Esa dirección está reservada.', $this->sugerir($slug));
        }

        if (Empresa::where('slug', $slug)->withTrashed()->exists()) {
            return $this->no('Ya hay un salón con esa dirección.', $this->sugerir($slug));
        }

        return ['ok' => true, 'motivo' => null, 'sugerencia' => null];
    }

    protected function no(string $motivo, ?string $sugerencia = null): array
    {
        return ['ok' => false, 'motivo' => $motivo, 'sugerencia' => $sugerencia];
    }

    /** Propone una variante libre, para no dejar al cliente en un callejón. */
    protected function sugerir(string $slug): ?string
    {
        foreach (range(2, 20) as $numero) {
            $intento = $slug . $numero;

            if (! Empresa::where('slug', $intento)->withTrashed()->exists()
                && ! in_array($intento, self::RESERVADOS, true)) {
                return $intento;
            }
        }

        return null;
    }

    /** Convierte el nombre del salón en un subdominio propuesto. */
    public function proponerSlug(string $nombre): string
    {
        $base = Str::slug($nombre);

        if (mb_strlen($base) < 3) {
            $base = 'salon-' . Str::lower(Str::random(5));
        }

        $base = mb_substr($base, 0, 40);

        return $this->comprobarSlug($base)['ok'] ? $base : ($this->sugerir($base) ?? $base);
    }

    // ------------------------------------------------------------------
    // Alta
    // ------------------------------------------------------------------

    /**
     * Crea el salón completo.
     *
     * @return array{empresa: Empresa, pin: string, password: string}
     */
    public function crear(
        Cuenta $cuenta,
        string $slug,
        string $nombreComercial,
        ?Plan $plan = null,
    ): array {
        $slug = strtolower(trim($slug));

        $comprobacion = $this->comprobarSlug($slug);

        if (! $comprobacion['ok']) {
            throw new RuntimeException($comprobacion['motivo']);
        }

        if (! $cuenta->email_verified_at) {
            throw new RuntimeException(
                'Confirma tu correo antes de crear el salón.'
            );
        }

        $plan ??= Plan::where('slug', 'prueba')->first() ?? Plan::orderBy('id')->first();

        $empresa = null;

        try {
            /**
             * Al crear la empresa, stancl/tenancy dispara la cadena que
             * crea la base de datos, la migra y la siembra. Es asíncrona
             * en configuración por defecto, pero aquí corre en la misma
             * petición porque las tareas no están en cola.
             */
            $empresa = Empresa::create([
                'slug'             => $slug,
                'nombre_comercial' => $nombreComercial,
                'email'            => $cuenta->email,
                'telefono'         => $cuenta->telefono,
                'cuenta_id'        => $cuenta->id,
                'plan_id'          => $plan?->id,
                'estado'           => 'PRUEBA',
                'prueba_hasta'     => now()->addDays((int) ($plan->dias_prueba ?? 30)),

                // Hasta que termine el asistente, el salón no está listo
                'configurada_en'   => null,
            ]);

            $empresa->domains()->create([
                'domain' => $slug . '.' . $this->dominioBase(),
            ]);

            $credenciales = $this->crearPropietario($empresa, $cuenta);

            Log::info('Salón creado', ['slug' => $slug, 'cuenta' => $cuenta->email]);

            return [
                'empresa'  => $empresa->fresh(),
                'pin'      => $credenciales['pin'],
                'password' => $credenciales['password'],
            ];

        } catch (\Throwable $e) {
            /**
             * Limpieza.
             *
             * Sin esto, un fallo a mitad deja el subdominio ocupado por
             * una empresa inservible, y el cliente no puede reintentar
             * con el mismo nombre. Se borra en orden inverso y se
             * ignoran los fallos de la propia limpieza: si la base nunca
             * llegó a crearse, borrarla dará error y no importa.
             */
            if ($empresa) {
                Log::error('Alta fallida, limpiando', [
                    'slug'  => $slug,
                    'error' => $e->getMessage(),
                ]);

                try {
                    (new DeleteDatabase($empresa))->handle();
                } catch (\Throwable) {
                }

                try {
                    $empresa->domains()->delete();
                    $empresa->forceDelete();
                } catch (\Throwable) {
                }
            }

            throw new RuntimeException(
                'No hemos podido crear el salón. No se ha guardado nada a medias: '
                . 'puedes volver a intentarlo con el mismo nombre.',
                0,
                $e,
            );
        }
    }

    /**
     * Da de alta al propietario dentro del salón recién creado.
     *
     * Las credenciales se devuelven en claro UNA vez, para enseñárselas.
     * Se guardan cifradas, así que después no hay forma de recuperarlas.
     */
    protected function crearPropietario(Empresa $empresa, Cuenta $cuenta): array
    {
        $pin = (string) random_int(1000, 9999);
        $password = Str::random(12);

        $empresa->run(function () use ($cuenta, $pin, $password) {
            $perfil = Perfil::where('clave', 'propietario')->first()
                ?? Perfil::orderBy('id')->first();

            if (! $perfil) {
                throw new RuntimeException(
                    'La base del salón se creó sin perfiles. Revisa PerfilesSeeder.'
                );
            }

            Usuario::create([
                'nombre'         => $cuenta->nombre,
                'email'          => $cuenta->email,
                'perfil_id'      => $perfil->id,
                'es_profesional' => true,
                'ficha_jornada'  => false,
                'pin'            => $pin,
                'password'       => $password,
                'estado'         => 'ACTIVO',
            ]);
        });

        return ['pin' => $pin, 'password' => $password];
    }

    protected function dominioBase(): string
    {
        $centrales = config('tenancy.central_domains', []);

        // El primero que no sea www ni admin
        foreach ($centrales as $dominio) {
            if (! str_starts_with($dominio, 'www.') && ! str_starts_with($dominio, 'admin.')) {
                return $dominio;
            }
        }

        return $centrales[0] ?? 'climacopos.com';
    }

    // ------------------------------------------------------------------
    // Configuración inicial
    // ------------------------------------------------------------------

    /** Marca el salón como configurado y listo para usarse. */
    public function marcarConfigurada(Empresa $empresa): void
    {
        $empresa->update(['configurada_en' => now()]);
    }
}
