<?php

declare(strict_types=1);

use App\Models\Dominio;
use App\Models\Empresa;

return [
    'tenant_model' => Empresa::class,
    'domain_model' => Dominio::class,

    /**
     * null porque 'empresas.id' es bigint autoincremental.
     * Con UUIDGenerator el paquete trataria la clave primaria como texto
     * y romperia el INSERT. El uuid publico lo genera el propio modelo.
     */
    'id_generator' => null,

    'central_domains' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CENTRAL_DOMAINS', 'climacopos.test,www.climacopos.test,admin.climacopos.test'))
    )),

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null,

        'prefix' => 'climacopos_emp_',
        'suffix' => '',

        'managers' => [
            'sqlite'  => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql'   => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'mariadb' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql'   => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'empresa',
    ],

    'filesystem' => [
        'suffix_base' => 'empresa',
        'disks' => [
            'local',
            'public',
        ],

        'root_override' => [
            'local'  => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],

        'suffix_storage_path' => true,

        /**
         * CORRECCION: false.
         *
         * Con true, asset() reescribe TODAS las URLs a /tenancy/assets/,
         * incluidos los ficheros globales de la aplicacion (CSS y JS de
         * public/), que dejan de cargar.
         *
         * Con false la regla queda clara:
         *   asset()        -> ficheros de la aplicacion, iguales para todos
         *   tenant_asset() -> ficheros de un salon concreto
         *
         * Las fotos de articulos y clientes siguen aisladas por empresa
         * porque usan tenant_asset() de forma explicita en sus modelos.
         */
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'empresa',
        'prefixed_connections' => [],
    ],

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,   // Fase 9
    ],

    'routes' => true,

    'migration_parameters' => [
        '--force'    => true,
        '--path'     => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
	'--force' => true,
    ],
];
