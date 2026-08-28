# CLIMACO POS — Fase 21 · Web corporativa y área de clientes

La cara pública de `climacopos.com`: tres productos, registro y descargas.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
php artisan db:seed --class=ProductosSeeder
```

Ctrl+Shift+R. **Requiere dos pasos manuales**, apartado 4.

---

## 2. Por qué NO va en otro directorio

Lo comentaste, y conviene dejarlo escrito porque es una decisión de fondo.

`climacopos.com` es el **dominio central** del multi-tenant, y `{salon}.climacopos.com` cada salón. Son la misma instalación de Laravel: `stancl/tenancy` elige `routes/web.php` o `routes/tenant.php` según el dominio de la petición.

Separar la web en otra instalación traería dos `vendor/`, dos configuraciones, dos despliegues... y sobre todo **rompería la cuenta única**. Las cuentas viven en `climacopos_central`; si la web fuera aparte, compartirlas exigiría montar una API entre ambas para algo que ya funciona.

---

## 3. Lo que hay

**Portada** con las tres soluciones, cada una con su color y su sector.

**Ficha por producto** en `/soluciones/restaurant`, `/gym` y `/beauty`. Los textos salen de la tabla `productos`, no del código: cambiar una frase comercial no debería exigir un despliegue.

**Registro con verificación por correo.** Una cuenta para los tres productos.

**Área de clientes** con descargas, historial y datos.

**Descargas servidas por PHP**, no enlazadas directamente. Así se exige sesión y se registra quién descarga qué versión: cuando un cliente llame con un problema, la primera pregunta es siempre «qué versión tienes», y ahora la respuesta está en la base de datos.

---

## 4. Dos pasos manuales

### 4.1 Guardia de autenticación `cuenta`

En `config/auth.php`, dentro de `guards`:

```php
'cuenta' => [
    'driver'   => 'session',
    'provider' => 'cuentas',
],
```

Y en `providers`:

```php
'cuentas' => [
    'driver' => 'eloquent',
    'model'  => App\Models\Cuenta::class,
],
```

Si ya existían de la Fase 8b, déjalos como están.

### 4.2 Disco de descargas

En `config/filesystems.php`, dentro de `disks`:

```php
'descargas' => [
    'driver' => 'local',
    'root'   => storage_path('app/descargas'),
    'throw'  => false,
],
```

Los instaladores van en `storage/app/descargas/`, **fuera de `public/`**. Si estuvieran en `public/`, cualquiera podría descargarlos con la URL directa y el registro de descargas no serviría de nada.

### 4.3 El método del correo

En `app/Services/CorreosPlataforma.php`, añade el método que está en `app/Services/verificarCuenta.txt`. No sustituyas el fichero: la clase ya tiene los métodos de impago y suspensión de la Fase 12.

---

## 5. Decisiones

### 5.1 La web en claro, el panel en oscuro

No es incoherencia. El panel se mira ocho horas en un mostrador; la web la visita un minuto alguien que no te conoce, y el claro transmite más confianza en ese contexto.

### 5.2 Verificación obligatoria antes de descargar

Con registro abierto, sin verificar cualquiera crearía cuentas con correos ajenos. Y de cara al SaaS es más serio todavía: cada alta acabará provisionando una base de datos, y no quieres que eso lo dispare un robot.

### 5.3 El enlace de verificación no caduca

Token largo y sin firma con caducidad. El correo tiene que funcionar aunque se abra tres días después y desde otro dispositivo. Un enlace que expira en una hora genera más soporte del que evita.

### 5.4 El reenvío no confirma si la cuenta existe

Responde lo mismo exista o no. Si dijera «ese correo no está registrado», cualquiera podría averiguar quién es cliente tuyo probando direcciones.

### 5.5 Un producto sin versiones no enseña botón

`tieneDescarga()` comprueba que exista versión publicada. Enseñar un botón que lleva a un error es peor que no enseñarlo.

---

## 6. Los textos legales están sin redactar

Las tres páginas legales muestran un aviso en rojo diciéndolo. **No las publiques así.**

Un aviso legal copiado de internet no te protege, y una política de privacidad genérica puede acarrear sanción: estás tratando datos personales de clientas de tus clientes, que es una posición de encargado del tratamiento con obligaciones concretas.

Encárgaselo a un asesor con los datos reales de la actividad. Es de las pocas cosas de este proyecto que no deberías resolver tú.

---

## 7. Lo que falta para que esto funcione de verdad

- **Alta automática de salones**: el cliente elige subdominio, se crea la base de datos y se siembra. Hoy sigue siendo `climacopos:crear-empresa` por consola. Es la siguiente fase y la más delicada.
- **Panel de administración de productos y versiones**: subir un instalador, marcar la versión actual, escribir las novedades. Hoy hay que insertarlo por base de datos.
- **Los instaladores**: no existen todavía. El de hostelería está en Python y hay que empaquetarlo; el de gimnasios ni siquiera lo hemos hablado.
- **Textos e imágenes reales**: los que hay son míos y describen lo que sé del producto. Revísalos, que la voz comercial es tuya.
