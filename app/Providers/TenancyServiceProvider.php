<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
            Events\CreatingTenant::class => [],

            /**
             * Alta de empresa: crea su base de datos, ejecuta las migraciones
             * de database/migrations/tenant y siembra EmpresaSeeder
             * (configuracion, perfiles de fabrica y catalogo plantilla).
             *
             * shouldBeQueued(false) en local para ver los errores al momento.
             * En produccion pasar a true con Supervisor corriendo un worker.
             */
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            Events\SavingTenant::class   => [],
            Events\TenantSaved::class    => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class  => [],
            Events\DeletingTenant::class => [],

            /**
             * ATENCION - CLIMACOPOS
             *
             * Aqui el paquete trae por defecto Jobs\DeleteDatabase.
             * Lo dejamos VACIO a proposito.
             *
             * El modelo Empresa usa SoftDeletes, y en Eloquent el evento
             * 'deleted' se dispara TAMBIEN en el borrado logico. Con el job
             * enganchado aqui, dar de baja a un salon borraria su base de
             * datos al instante, en contra del periodo de retencion de 90
             * dias que promete la plataforma.
             *
             * El borrado real se hace con:
             *   php artisan climacopos:purgar-empresa {id}
             */
            Events\TenantDeleted::class => [],

            // Dominios
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class  => [],
            Events\SavingDomain::class   => [],
            Events\DomainSaved::class    => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class  => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class  => [],

            // Base de datos
            Events\DatabaseCreated::class    => [],
            Events\DatabaseMigrated::class   => [],
            Events\DatabaseSeeded::class     => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class    => [],

            // Ciclo de vida del contexto de empresa
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class  => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class  => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class      => [],
            Events\TenancyBootstrapped::class       => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class  => [],

            // Sincronizacion de recursos entre central y empresa
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        /**
         * Funciones globales de la aplicacion: config_empresa(), etc.
         *
         * Se cargan desde aqui en lugar de por composer.json ("autoload.files")
         * para que un despliegue sea siempre copiar ficheros, sin tener que
         * ejecutar 'composer dump-autoload' ni editar composer.json a mano.
         *
         * Este proveedor ya esta registrado en bootstrap/providers.php y se
         * carga en toda peticion, incluida la consola.
         */
        $ayudantes = app_path('Support/ayudantes.php');

        if (file_exists($ayudantes)) {
            require_once $ayudantes;
        }
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            Middleware\PreventAccessFromCentralDomains::class,
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
