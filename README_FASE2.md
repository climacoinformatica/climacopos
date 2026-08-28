# CLIMACO POS — Fase 2 · Catálogo

Familias, servicios, productos, fotos, características, profesionales por servicio y plantillas de arranque por tipo de negocio.

Descomprime encima de `C:\xampp\htdocs\climacopos`, sobrescribiendo.

---

## 1. Instalación

```powershell
cd C:\xampp\htdocs\climacopos

# Enlace de storage (solo la primera vez)
php artisan storage:link

php artisan migrate          # añade tipo_negocio a empresas
php artisan tenants:migrate  # crea las tablas del catálogo
php artisan tenants:seed     # precarga el catálogo plantilla
php artisan optimize:clear
```

Y entra en `http://jectan.climacopos.test/panel` → **Catálogo**. Deberías encontrarte ya con las familias y servicios de peluquería precargados.

Si quieres probar otra plantilla, cambia el tipo de negocio de la empresa y vuelve a sembrar sobre una empresa nueva:

```powershell
php artisan climacopos:crear-empresa barberiapepe --nombre="Barberia Pepe"
```

Recuerda añadir el subdominio al fichero `hosts`.

---

## 2. Ficheros

```
app/Models/Familia.php
app/Models/Articulo.php               precios, duraciones, fianzas
app/Models/ArticuloFoto.php
app/Models/ArticuloAtributo.php
app/Models/Recurso.php
app/Services/GestorImagenes.php       subida y redimensionado con GD
app/Support/PlantillasCatalogo.php    catálogos por tipo de negocio
app/Http/Controllers/Panel/FamiliaController.php
app/Http/Controllers/Panel/ArticuloController.php
database/migrations/2026_01_03_000001_add_tipo_negocio_to_empresas.php
database/migrations/tenant/2026_01_03_100001_create_catalogo_tables.php
database/seeders/tenant/CatalogoPlantillaSeeder.php
database/seeders/EmpresaSeeder.php    SUSTITUYE al de Fase 1
public/css/panel.css                  hoja de estilos del panel
resources/views/panel/app.blade.php   layout con navegación
resources/views/panel/inicio.blade.php  SUSTITUYE al de Fase 1
resources/views/panel/catalogo/*.blade.php
routes/tenant.php                     SUSTITUYE al de Fase 1
tests/Feature/CatalogoTest.php
```

---

## 3. Decisiones de diseño

### 3.1 El precio se guarda con impuesto incluido

Es la decisión más importante de esta fase y condiciona toda la Fase 5.

En hostelería y en salones, el precio que se anuncia y el que se teclea en el TPV es el **precio final con impuesto**. Guardarlo así y calcular la base hacia atrás evita el problema clásico de los redondeos: si guardas la base y multiplicas, un corte a 22,00 € acaba mostrándose como 21,99 € o 22,01 € según el día.

```php
$articulo->precio          // 107,00 € — lo que paga el cliente
$articulo->baseImponible() // 100,00 €
$articulo->cuotaImpuesto() //   7,00 €
```

Hay un test que verifica que base + cuota siempre suman el precio exacto, con IGIC y con IVA.

El impuesto por defecto sale del `regimen_fiscal` de la empresa: 7% (IGIC general) en Canarias, 21% (IVA) en península.

### 3.2 Pausa intermedia en los servicios

Un tinte no ocupa al profesional durante los 65 minutos que dura. Son 20 min aplicando, 30 min de espera y 15 min de lavado y secado. Durante esos 30 minutos el profesional puede atender a otra clienta, y ahí está buena parte de la rentabilidad de un salón.

Por eso cada servicio tiene tres tiempos:

| Campo | Significado |
|---|---|
| `duracion_min` | Trabajo activo inicial |
| `tiempo_pausa_min` | Hueco en que el profesional queda **libre** |
| `tiempo_final_min` | Trabajo activo final |

La cita ocupa la suma de los tres en la agenda, pero el motor de huecos de la Fase 3 solo bloqueará al profesional en el primero y el tercero. Si esto no estuviera en el modelo desde ahora, habría que rehacer la agenda entera después.

### 3.3 Profesionales por servicio

Sin filas en `articulo_profesional`, el servicio lo hace cualquiera. Con filas, solo los marcados, y cada uno puede tener precio y duración propios — un estilista senior cobra más y tarda menos.

### 3.4 Fotos

Se redimensionan a 1200 px y se genera una miniatura de 320 px con GD, que ya viene en XAMPP. Sin Intervention Image ni dependencias extra.

Se corrige la **orientación EXIF**: las fotos hechas con móvil llegan giradas si no se lee ese dato, y en un catálogo de salón casi todas las fotos vienen del móvil.

Los ficheros van al disco `public`, que el `FilesystemTenancyBootstrapper` separa por empresa (`storage/empresa{id}/app/public/...`). Se sirven con `tenant_asset()`, que pasa por el middleware de tenancy: las fotos de un salón no son accesibles desde el subdominio de otro.

### 3.5 Borrado lógico de artículos

Un artículo borrado sigue existiendo para los tickets antiguos. Si se borrara de verdad, el histórico de ventas quedaría con líneas huérfanas y el libro de facturas VERI\*FACTU dejaría de cuadrar. Las familias, en cambio, no se pueden borrar si tienen artículos dentro: hay que desactivarlas.

### 3.6 Plantillas de arranque

Seis tipos de negocio con sus familias, servicios, duraciones y precios orientativos de Canarias. Un salón que se da de alta encuentra su catálogo hecho en lugar de una pantalla vacía, que es lo que más abandono provoca en el alta de un SaaS.

El seeder no hace nada si ya hay familias, así que se puede relanzar `tenants:seed` sin duplicar el catálogo de un salón en marcha.

---

## 4. Probar

```powershell
php artisan test --filter=CatalogoTest
```

Diecisiete pruebas: cálculo de base con IGIC y con IVA, cuadre de base + cuota, márgenes, duraciones con pausa, tarifas por profesional, fianzas fijas y porcentuales, plantillas de los seis tipos de negocio, stock bajo mínimo y borrado lógico.

---

## 5. También en este paquete

Los acentos de la interfaz, corregidos. Las vistas de la Fase 1 (`selector`, `vincular`, `reautenticar`) siguen sin ellos; las sustituiré en la Fase 3, cuando toque rehacer el layout para la agenda.

Las claves de permiso (`tpv.anular_ticket`) se quedan en ASCII a propósito: son identificadores internos, no texto visible. Lo que se muestra al usuario sale de `Permisos::catalogo()`, que sí lleva tildes.

---

## 6. Pendiente

- **Recursos** (cabinas, lavacabezas): la tabla y el modelo están, falta el CRUD. Son cuatro campos; lo añado con los ajustes de la Fase 6.
- **Packs**: la tabla `articulo_componentes` está creada, falta la interfaz para componerlos.
- **Reordenar arrastrando**: ahora el orden se teclea a mano. Funciona, pero con 40 servicios se hace pesado.
- **Importar catálogo desde Excel**: útil para salones que vienen de otro programa. Encaja mejor cuando haya clientes reales pidiéndolo.

---

## 7. Siguiente: Fase 3

Agenda: horarios de profesionales, excepciones, bloqueos, motor de huecos disponibles y calendario multi-profesional. Es la fase con más lógica del proyecto, porque el cálculo de disponibilidad tiene que respetar horarios, vacaciones, pausas intermedias y recursos limitados a la vez.
