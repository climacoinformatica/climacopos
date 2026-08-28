# CLIMACO POS — Fase 3b · Correcciones y automatización

Este paquete elimina las ediciones manuales de ficheros. Descomprime encima de `C:\xampp\htdocs\climacopos`, sobrescribiendo, y ejecuta dos scripts.

---

## 1. Qué hacer, en orden

### Paso 1 — Activar las extensiones de PHP

PowerShell **como administrador**:

```powershell
cd C:\xampp\htdocs\climacopos
powershell -ExecutionPolicy Bypass -File herramientas\activar_extensiones.ps1
```

Activa `gd`, `exif`, `fileinfo` y las demás que hacen falta, y ajusta los límites de subida para las fotos de móvil. Hace copia de seguridad de `php.ini` antes de tocarlo.

Después **reinicia Apache** desde el panel de XAMPP (Stop y Start).

### Paso 2 — Poner el proyecto al día

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

Ejecuta migraciones centrales y de empresa, siembra los datos de arranque, crea el enlace de storage y limpia las cachés. Se puede relanzar cuantas veces quieras.

Eso es todo. No hay que editar `composer.json`, ni `config/tenancy.php`, ni `php.ini` a mano.

---

## 2. Qué corrige

### 2.1 Ya no hace falta tocar `composer.json`

El fichero `app/Support/ayudantes.php`, donde vive `config_empresa()`, ahora se carga desde `TenancyServiceProvider::register()` con un `require_once`, en lugar de por `autoload.files`.

Ese proveedor ya está registrado en `bootstrap/providers.php` y se ejecuta en toda petición, incluida la consola, así que el efecto es el mismo. La ventaja: desplegar vuelve a ser **copiar ficheros**, sin `composer dump-autoload` ni ediciones de JSON.

### 2.2 `asset_helper_tenancy` en `false`

Era lo que te dejó el panel sin estilos: con `true`, `asset()` reescribía todas las URLs a `/tenancy/assets/`, incluidos el CSS y el JS de `public/`.

Ahora la regla es clara:

- `asset()` → ficheros de la aplicación, iguales para todos los salones
- `tenant_asset()` → ficheros de un salón concreto

Las fotos siguen aisladas por empresa porque los modelos usan `tenant_asset()` de forma explícita.

### 2.3 `SeedDatabase` activado

El pipeline de alta de empresa ya siembra configuración, perfiles de fábrica y catálogo plantilla. Antes estaba comentado a la espera de que `EmpresaSeeder` existiera.

### 2.4 Acentos en las vistas del selector

`selector`, `vincular`, `reautenticar` y su plantilla base, reescritos con la ortografía correcta.

### 2.5 Extensiones de PHP

`gd` estaba desactivada y por eso reventaba la subida de fotos. El script la activa junto con `exif` (orientación de fotos de móvil) y `fileinfo`.

---

## 3. Ficheros

```
app/Providers/TenancyServiceProvider.php    SUSTITUYE
config/tenancy.php                          SUSTITUYE
resources/views/panel/base.blade.php        SUSTITUYE
resources/views/panel/selector.blade.php    SUSTITUYE
resources/views/panel/vincular.blade.php    SUSTITUYE
resources/views/panel/reautenticar.blade.php SUSTITUYE
herramientas/activar_extensiones.ps1        nuevo
herramientas/actualizar.ps1                 nuevo
```

De aquí en adelante, cada fase incluirá su `herramientas\actualizar.ps1` puesto al día, para que la instalación sea siempre: descomprimir y ejecutar un script.

---

## 4. Comprobación

```powershell
php -m | Select-String "gd|exif|fileinfo"
php artisan test --filter=MotorHuecosTest
```

Y en el navegador, `http://jectan.climacopos.test/panel`:

- El panel con estilos, la agenda en el menú.
- **Agenda** → columnas por profesional. Sale vacía hasta que configures horarios.
- **Horarios** → rellena la semana de cada profesional.
- **Catálogo** → sube una foto a un artículo; ahora debería funcionar.
