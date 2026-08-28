# CLIMACO POS — Fase 8b · Panel de administración de la plataforma

Las claves de Stripe se configuran desde una pantalla, no editando ficheros.

---

## 1. Instalación

### 1.1 Registrar el middleware

En `bootstrap/app.php`, añade a los alias que ya tienes:

```php
'superadmin' => \App\Http\Middleware\AutenticarSuperadmin::class,
```

### 1.2 Añadir el subdominio de administración

En `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1   admin.climacopos.test
```

Ya debería estar en `CENTRAL_DOMAINS` del `.env`. Compruébalo:

```ini
CENTRAL_DOMAINS=climacopos.test,www.climacopos.test,admin.climacopos.test
```

### 1.3 Lo demás

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
php artisan climacopos:crear-superadmin --email=tu@correo.com --nombre="Tu nombre"
```

Anota la contraseña que imprime y entra en `http://admin.climacopos.test/acceso`.

---

## 2. Qué cambia

**Las claves de Stripe ya no van en el `.env`.** Se guardan en la base central, cifradas con la `APP_KEY`, y se editan desde *Administración → Pagos*.

Eso significa que montar la plataforma ya no exige abrir un fichero por SSH ni saber qué es una variable de entorno. El `.env` sigue funcionando como respaldo para instalaciones antiguas, pero lo que se guarde en la base manda.

### 2.1 Tres niveles de configuración, y cada uno en su sitio

| Nivel | Quién lo toca | Dónde |
|---|---|---|
| **Plataforma** | Tú | `admin.climacopos.com` |
| **Empresa** | El dueño del salón | su panel → Ajustes |
| **Terminal** | El dueño del salón | su panel → Hardware |

Un dueño de peluquería **nunca** ve las claves de la plataforma. Solo conecta su propia cuenta de Stripe, que es suya y va aparte.

### 2.2 Los secretos no se muestran nunca

Una vez guardada, la clave secreta no vuelve a aparecer en pantalla: el campo sale vacío con la etiqueta «guardada». Dejarlo vacío al guardar el formulario **no borra** lo que había, que es el comportamiento que espera cualquiera que solo quería cambiar la comisión.

### 2.3 Guía integrada

Si no hay claves configuradas, la pantalla muestra los pasos para conseguirlas en Stripe: dónde registrarse, dónde están las claves, cómo activar el modo prueba y cómo dar de alta el webhook. Con la URL del webhook lista para copiar.

### 2.4 Validaciones que evitan el error típico

- Las claves se validan por formato: `pk_`, `sk_`, `whsec_`. Pegar una donde no toca es el error más común.
- Si se mezcla una clave de pruebas con otra de producción, avisa. Es un fallo que si no, se descubre con el primer cliente real intentando pagar.
- El botón «Probar conexión» consulta el saldo a Stripe: la llamada más barata de su API, que no mueve dinero ni modifica nada, y dice si la clave sirve y en qué modo está.

### 2.5 Limitación de intentos en el acceso

Cinco por minuto y dirección IP. Esta pantalla da acceso a la configuración de cobro de todos los salones.

---

## 3. El panel

**Empresas**: todas las altas con su plan, estado, si cobran online y un enlace a su portal. Al entrar en una se ven sus contadores —usuarios, clientes, reservas, ventas— leyendo su base de datos.

**Pagos**: las claves y la comisión.

---

## 4. Probar

```powershell
php artisan test --filter=ConfigPlataformaTest
```

Doce pruebas: que los secretos se guardan cifrados, que se leen descifrados, que se puede saber si existen sin leerlos, y que una cuenta normal no es superadministrador.

---

## 5. Pendiente para la Fase 9

- Suscripciones de los salones con Stripe Billing.
- Impersonación: entrar en el panel de un salón desde aquí para dar soporte, con registro en auditoría.
- Configuración del correo saliente, también desde esta pantalla y no desde el `.env`.
- Métricas de plataforma: facturación recurrente, altas y bajas.
