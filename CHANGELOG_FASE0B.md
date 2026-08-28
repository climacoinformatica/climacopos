# CLIMACO POS — Fase 0b · Correcciones para stancl/tenancy 3.10.1

Descomprime encima de `C:\xampp\htdocs\climacopos`, sobrescribiendo.

## Ficheros

| Fichero | Acción |
|---|---|
| `config/tenancy.php` | **Sustituye** al generado por `tenancy:install` |
| `app/Providers/TenancyServiceProvider.php` | **Sustituye** al generado por `tenancy:install` |
| `app/Models/Empresa.php` | **Sustituye** al del ZIP de la Fase 0 (corregido) |
| `app/Console/Commands/PurgarEmpresa.php` | Nuevo |
| `tests/Feature/AislamientoEmpresasTest.php` | **Sustituye** al del ZIP de la Fase 0 |

## Cambios

### 1. Clave primaria numérica

`tenancy:install` deja `'id_generator' => Stancl\Tenancy\UUIDGenerator::class`, que hace que el paquete trate la clave primaria como texto. Nuestra tabla `empresas` usa `id` bigint autoincremental.

- `config/tenancy.php`: `'id_generator' => null`
- `Empresa.php`: se revierten `$incrementing = true`, `$keyType = 'int'`, `$primaryKey = 'id'`, que el modelo `Tenant` del paquete deja preparados para UUID.

El `uuid` público sigue existiendo como columna aparte, generado en el evento `creating`. Se usa para URLs y rutas de almacenamiento, no como clave.

### 2. El borrado lógico ya no destruye la base de datos

**Este era un fallo grave.** El provider viene con:

```php
Events\TenantDeleted::class => [ ... Jobs\DeleteDatabase::class ... ],
```

Y `Empresa` usa `SoftDeletes`. En Eloquent el evento `deleted` se dispara **también** al hacer borrado lógico, así que dar de baja a un salón habría eliminado su base de datos del servidor al instante — justo lo contrario del periodo de retención de 90 días que promete la plataforma.

Ahora `TenantDeleted` queda vacío y el borrado real es explícito:

```powershell
php artisan climacopos:purgar-empresa 3
php artisan climacopos:purgar-empresa --caducadas
```

`--caducadas` purga las que llevan de baja más de `climacopos.dias_hasta_borrado` (90 por defecto). Es el comando que irá al scheduler en producción, con confirmación interactiva o `--no-interaction` según decidas.

Hay un test de regresión que lo vigila: `test_la_baja_logica_conserva_la_base_de_datos`.

### 3. `central_domains` desde el `.env`

El generado traía `127.0.0.1` y `localhost`. Ahora lee `CENTRAL_DOMAINS`, así que el mismo código vale en local (`.test`) y en producción (`.com`) sin tocar el fichero.

### 4. Prefijos de caché, ficheros y Redis

Pasan de `tenant` a `empresa`, por coherencia con el resto del esquema. Es cosmético, pero afecta a nombres de carpetas en `storage/`, así que conviene fijarlo antes de que haya datos.

### 5. `seeder_parameters`

Apunta a `Database\Seeders\EmpresaSeeder`, que se creará en la Fase 1 para sembrar los perfiles de fábrica en cada base nueva. Mientras no exista, `Jobs\SeedDatabase` sigue comentado en el provider, así que no se ejecuta.

## Comprobación

```powershell
php artisan migrate
php artisan db:seed --class=PlanesSeeder
php artisan climacopos:crear-empresa jectan --nombre="Peluqueria Jectan"
php artisan climacopos:crear-empresa demo   --nombre="Salon Demo"
php artisan test --filter=AislamientoEmpresasTest
```

Después, en phpMyAdmin deben existir `climacopos_central`, `climacopos_emp_1` y `climacopos_emp_2`, y `http://jectan.climacopos.test` devolver el JSON con `base_datos: climacopos_emp_1`.

## Pendiente antes de arrancar

- Borrar `database/migrations/*create_tenants_table.php` y `*create_domains_table.php`.
- Crear `climacopos_central` en phpMyAdmin.
- Ajustar el `.env` (sección 4 del README de la Fase 0), con `SESSION_DOMAIN=null`.
- Añadir `jectan.climacopos.test` y `demo.climacopos.test` al fichero `hosts`.
- Confirmar que `bootstrap/providers.php` incluye `App\Providers\TenancyServiceProvider::class`.
