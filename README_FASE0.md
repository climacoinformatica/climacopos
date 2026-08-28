# CLIMACO POS — Plataforma de salones · Fase 0

Esqueleto multi-empresa: una base de datos por salón, resolución por subdominio, creación de empresas en caliente y test de aislamiento.

Dominio: `climacopos.com` (local: `climacopos.test`)

---

## 1. Qué contiene este ZIP

```
config/climacopos.php                     configuracion propia de la plataforma
app/Models/Empresa.php                    el tenant
app/Models/Dominio.php                    subdominios y dominios propios
app/Models/Plan.php
app/Models/Cuenta.php                     quien contrata y paga (dominio central)
app/Support/Slug.php                      validacion y slugs reservados
app/Console/Commands/CrearEmpresa.php     alta de empresas por consola
database/migrations/                      esquema CENTRAL
  2026_01_01_000001_create_planes_table.php
  2026_01_01_000002_create_empresas_table.php
  2026_01_01_000003_create_dominios_table.php
  2026_01_01_000004_create_cuentas_table.php
database/migrations/tenant/               esquema de CADA EMPRESA
  2026_01_01_100001_create_config_table.php
  2026_01_01_100002_create_terminales_table.php
database/seeders/PlanesSeeder.php
routes/tenant.php                         rutas de humo
tests/Feature/AislamientoEmpresasTest.php el test que no se borra nunca
```

Los ficheros van en sus rutas definitivas: copia el contenido del ZIP sobre la raíz del proyecto Laravel sin renombrar nada.

---

## 2. Crear el proyecto

```bat
cd C:\xampp\htdocs
composer create-project laravel/laravel climacopos
cd climacopos
composer require stancl/tenancy
php artisan tenancy:install
```

Luego copia encima el contenido de este ZIP.

**Importante**: `php artisan tenancy:install` crea sus propias migraciones `create_tenants_table` y `create_domains_table` en `database/migrations/`. **Bórralas**, porque las nuestras (`empresas` y `dominios`) las sustituyen.

---

## 3. Base de datos

En phpMyAdmin:

```sql
CREATE DATABASE climacopos_central
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Las bases de cada salón (`climacopos_emp_1`, `climacopos_emp_2`…) las crea la aplicación sola. El usuario de MySQL necesita permisos `CREATE`, `DROP` y `GRANT OPTION`; con el `root` de XAMPP ya los tienes.

---

## 4. `.env`

```ini
APP_NAME="CLIMACO POS"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://climacopos.test
APP_TIMEZONE=Atlantic/Canary
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=climacopos_central
DB_USERNAME=root
DB_PASSWORD=

DOMINIO_BASE=climacopos.test
CENTRAL_DOMAINS=climacopos.test,www.climacopos.test,admin.climacopos.test

# CRITICO: cookie host-only.
# Con .climacopos.test la sesion de un salon viajaria al resto de subdominios.
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

QUEUE_CONNECTION=database
CACHE_STORE=database
```

---

## 5. Cableado de tenancy

### 5.1 `config/tenancy.php`

```php
'tenant_model' => \App\Models\Empresa::class,
'domain_model' => \App\Models\Dominio::class,

'central_domains' => explode(',', env('CENTRAL_DOMAINS', 'climacopos.test')),

'database' => [
    'central_connection' => env('DB_CONNECTION', 'mysql'),
    'template_tenant_connection' => null,

    // No se usan porque Empresa fija 'tenancy_db_name' de forma explicita,
    // pero conviene dejarlos coherentes.
    'prefix' => 'climacopos_emp_',
    'suffix' => '',

    'managers' => [
        'mysql' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
    ],
],

'migration_parameters' => [
    '--force' => true,
    '--path'  => [database_path('migrations/tenant')],
    '--realpath' => true,
],
```

### 5.2 Eventos: crear y borrar la base de datos

En `app/Providers/TenancyServiceProvider.php`, en `$events`, deja activos como mínimo:

```php
Events\TenantCreated::class => [
    JobPipeline::make([
        Jobs\CreateDatabase::class,
        Jobs\MigrateDatabase::class,
        Jobs\SeedDatabase::class,     // opcional, para perfiles de fabrica en Fase 1
    ])->send(fn (Events\TenantCreated $event) => $event->tenant)
      ->shouldBeQueued(false),        // en produccion: true
],

Events\DeletingTenant::class => [
    Jobs\DeleteDatabase::class,
],
```

`shouldBeQueued(false)` en local para que el alta sea síncrona y veas los errores al momento. En producción, `true` con Supervisor.

### 5.3 Registrar las rutas de tenant

**Con tenancy v3 y Laravel 11/12** — en `app/Providers/TenancyServiceProvider.php`, método `boot()`:

```php
$this->app->booted(function () {
    Route::middleware('web')
         ->group(base_path('routes/tenant.php'));
});
```

**Con tenancy v4** — puedes usar la identificación temprana en el *stack* del kernel (`bootstrap/app.php`), que evita que los constructores de controlador se ejecuten antes de resolver la empresa. Consulta `v4.tenancyforlaravel.com` para la sintaxis exacta de tu versión instalada; el resto de este paquete no cambia.

Y en `routes/web.php` deja **solo** lo del dominio central (web comercial, registro, `admin.`).

### 5.4 Registrar el comando

En Laravel 11+ los comandos de `app/Console/Commands` se registran solos. Si no aparece, comprueba `bootstrap/app.php`.

---

## 6. Arrancar

```bat
php artisan migrate
php artisan db:seed --class=PlanesSeeder
php artisan climacopos:crear-empresa jectan --nombre="Peluqueria Jectan"
php artisan climacopos:crear-empresa demo   --nombre="Salon Demo"
```

Añade a `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1   climacopos.test
127.0.0.1   www.climacopos.test
127.0.0.1   admin.climacopos.test
127.0.0.1   jectan.climacopos.test
127.0.0.1   demo.climacopos.test
```

Comprobación:

| URL | Debe responder |
|---|---|
| `http://jectan.climacopos.test` | JSON con `base_datos: climacopos_emp_1` |
| `http://demo.climacopos.test` | JSON con `base_datos: climacopos_emp_2` |
| `http://climacopos.test` | Bienvenida de Laravel (dominio central) |
| `http://otro.climacopos.test` | 404 de tenant no encontrado |

Y en phpMyAdmin deben aparecer `climacopos_central`, `climacopos_emp_1` y `climacopos_emp_2`.

---

## 7. El test de aislamiento

```bat
php artisan test --filter=AislamientoEmpresasTest
```

Crea dos empresas reales, escribe datos en cada una y verifica que no se ven entre sí. **No uses `RefreshDatabase` en él**: necesita crear bases de datos de verdad.

Este test debe pasar antes de cada despliegue. Es la red de seguridad de todo el producto: si algún día falla, hay una fuga de datos entre salones.

---

## 8. Decisiones que quedan fijadas aquí

- **El nombre de la base de datos usa el `id`, no el `slug`.** El slug puede cambiar (el salón se renombra); el nombre de la base de datos, jamás. Por eso `Empresa::created()` fija `tenancy_db_name` después del INSERT.
- **`cuentas` (central) ≠ `usuarios` (empresa).** La cuenta contrata y paga desde `climacopos.com`. Los usuarios son los empleados que entran con PIN en el salón y viven dentro de la base de la empresa. Son dos guards distintos y no deben mezclarse.
- **Los slugs se comprueban contra papelera** (`withTrashed`): un salón dado de baja no libera su subdominio inmediatamente, para que nadie herede sus enlaces de Instagram.
- **`dominios.tenant_id`** conserva el nombre en inglés porque es el que espera el modelo `Domain` de stancl. Es la única concesión de nomenclatura del esquema.

---

## 9. Siguiente: Fase 1

Perfiles y permisos, tabla `usuarios` de empresa, selector de usuario con PIN, invitación de empleados por email y registro de empresa desde `climacopos.com` con el asistente de onboarding.
