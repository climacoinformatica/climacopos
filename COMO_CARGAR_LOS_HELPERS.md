# Los helpers globales

Si `config_terminal()` o `config_empresa()` dan «undefined function», es que
el fichero `app/Support/ayudantes.php` no se está cargando.

## Comprobar

```powershell
Get-Item app\Support\ayudantes.php
Select-String -Path app\Providers\TenancyServiceProvider.php -Pattern "ayudantes"
```

## Cómo debe cargarse

En `app/Providers/TenancyServiceProvider.php`, dentro de `register()`:

```php
public function register(): void
{
    /**
     * Helpers globales.
     *
     * Se cargan aquí con require_once y no por composer.json porque así
     * desplegar sigue siendo copiar ficheros: no hace falta un
     * `composer dump-autoload` en el servidor cada vez.
     */
    require_once app_path('Support/ayudantes.php');
}
```

Después:

```powershell
php artisan optimize:clear
```

## Comprobación rápida

```powershell
php artisan tinker --execute="echo function_exists('config_terminal') ? 'OK' : 'FALTA';"
```
