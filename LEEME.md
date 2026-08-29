# PDF y envío por correo

Cierres y listados de facturas descargables y enviables.

---

## 1. Ficheros

**Nuevos:**

```
app/Services/GeneradorPdf.php
app/Http/Controllers/Panel/DocumentosController.php
resources/views/pdf/base.blade.php
resources/views/pdf/cierre.blade.php
resources/views/pdf/facturas.blade.php
resources/views/panel/documentos/facturas.blade.php
resources/views/correo/plataforma/documento.blade.php
```

**Completos, para sustituir:**

```
routes/tenant.php
resources/views/panel/caja/cierre.blade.php
```

Después:

```bash
cd /var/www/climacopos
php artisan optimize:clear
rm -f storage/framework/views/*.php
sudo systemctl restart php8.4-fpm
```

---

## 2. Qué queda

**En el cierre de jornada**, tres botones: descargar PDF, enviar por correo e imprimir. Este último ofrece el cierre y el parte por separado, como acordamos.

**Una pantalla nueva de facturas** en `/panel/documentos/facturas`, con atajos por trimestre —que es lo que pide la gestoría— y los mismos botones.

Falta enlazarla en el menú. En `panel/app.blade.php`, junto a Informes:

```blade
<a href="{{ route('panel.documentos.facturas') }}" @class(['activo' => request()->routeIs('panel.documentos.*')])>Facturas</a>
```

Pásame esa vista y te la devuelvo completa.

---

## 3. Decisiones

### 3.1 Se generan al vuelo, no se guardan

Los datos de un cierre ya no cambian: los tickets quedaron marcados al cerrarlo. El PDF de hoy y el de dentro de un mes salen iguales.

### 3.2 El desglose por tipo de impuesto

En el listado de facturas, además del total. Es lo que pide la gestoría para el modelo trimestral: no le vale el total, necesita cuánto hay a cada tipo.

### 3.3 El reparto por profesional NO va en el cierre

Va en el parte de trabajo, aparte. El cierre lo maneja quien cuadra el efectivo; lo que factura cada persona es información laboral.

### 3.4 Los correos salen por el SMTP de la plataforma

No por el del salón. Son documentos internos que van al gestor o al propietario, no avisos a una clienta.

### 3.5 Numeración de páginas en el PDF

Con contadores de CSS. En un listado de un trimestre importa: un papel suelto sin número de página no se sabe de dónde salió.

### 3.6 La formación queda fuera sola

Por el global scope. No tiene valor fiscal y no puede aparecer en nada que vea la gestoría. El PDF lo dice explícitamente al pie.

---

## 4. Probar

**El cierre:** entra en uno existente desde Caja y prueba los tres botones.

**Las facturas:** `/panel/documentos/facturas`, elige «Trimestre pasado» y descarga.

Si el PDF sale con los acentos rotos, dímelo: sería cuestión de la fuente.

---

## 5. Pendiente

- **El parte de trabajo en PDF**, que ahora solo se imprime.
- **Los informes de producción** en PDF.
- **Recordar la dirección de envío**, para no teclear la de la gestoría cada trimestre.
