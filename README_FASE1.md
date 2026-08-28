# CLIMACO POS — Fase 1 · Usuarios, perfiles y acceso al salón

Modelo de acceso **opción C**: terminal vinculado una vez, PIN en el día a día, contraseña para lo sensible.

Descomprime encima de `C:\xampp\htdocs\climacopos`, sobrescribiendo.

---

## 1. Cómo funciona el acceso

```
┌─ Paso 1 ─ UNA VEZ POR EQUIPO ──────────────────────────────────┐
│  /panel/vincular                                                │
│  Email + contraseña de alguien con permiso terminales.vincular  │
│  → cookie httpOnly de 1 año con el token del terminal           │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─ Paso 2 ─ CADA TURNO ──────────────────────────────────────────┐
│  /panel/selector                                                │
│  Rejilla de fotos → PIN de 4-8 dígitos                          │
│  → sesión de navegador                                          │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─ Paso 3 ─ ACCIONES SENSIBLES ──────────────────────────────────┐
│  Ajustes, informes, cierre de caja, anulaciones, devoluciones,  │
│  borrado de formación, gestión de usuarios, vincular terminales │
│  → contraseña completa, válida 15 minutos                       │
└─────────────────────────────────────────────────────────────────┘
```

La lista exacta de lo que exige contraseña está en `Permisos::EXIGEN_PASSWORD`. Añadir o quitar un permiso de esa constante cambia el comportamiento en toda la aplicación, sin tocar rutas.

**Seguridad del PIN**: se guarda hasheado con bcrypt, nunca en claro. Cinco fallos bloquean al usuario cinco minutos, y durante el bloqueo ni siquiera el PIN correcto entra. Todo intento fallido queda en `log_auditoria`.

---

## 2. Ficheros

```
app/Support/Permisos.php                          catálogo único de permisos
app/Support/SesionSalon.php                       lógica de los tres pasos
app/Models/Perfil.php
app/Models/Usuario.php                            empleado del salón
app/Models/UsuarioHorario.php
app/Models/UsuarioExcepcion.php
app/Models/Invitacion.php
app/Models/Terminal.php
app/Models/TerminalConfig.php
app/Models/TerminalVinculo.php
app/Models/Auditoria.php
app/Http/Middleware/VerificarTerminal.php
app/Http/Middleware/AutenticarSalon.php
app/Http/Middleware/VerificarPermiso.php
app/Http/Controllers/Panel/SelectorController.php
app/Http/Controllers/Panel/TerminalController.php
app/Http/Controllers/Panel/ReautenticacionController.php
app/Console/Commands/CrearUsuario.php
database/migrations/tenant/2026_01_02_100001_create_perfiles_usuarios_tables.php
database/migrations/tenant/2026_01_02_100002_create_usuario_horarios_tables.php
database/migrations/tenant/2026_01_02_100003_create_terminal_vinculos_table.php
database/seeders/EmpresaSeeder.php                seeder raíz de cada empresa
database/seeders/tenant/PerfilesSeeder.php        los 5 perfiles de fábrica
resources/views/panel/base.blade.php
resources/views/panel/selector.blade.php          rejilla + teclado PIN
resources/views/panel/vincular.blade.php
resources/views/panel/reautenticar.blade.php
resources/views/panel/inicio.blade.php
routes/tenant.php                                 SUSTITUYE al de Fase 0
tests/Feature/PermisosUsuarioTest.php
```

---

## 3. Cableado manual

### 3.1 Registrar los alias de middleware

En `bootstrap/app.php`, dentro de `->withMiddleware(function (Middleware $middleware) {`:

```php
$middleware->alias([
    'terminal' => \App\Http\Middleware\VerificarTerminal::class,
    'salon'    => \App\Http\Middleware\AutenticarSalon::class,
    'permiso'  => \App\Http\Middleware\VerificarPermiso::class,
]);
```

Sin esto, las rutas del panel darán error de middleware desconocido.

### 3.2 Activar el seeder de empresas nuevas

En `app/Providers/TenancyServiceProvider.php`, descomenta `Jobs\SeedDatabase::class` en el pipeline de `TenantCreated`:

```php
Events\TenantCreated::class => [
    JobPipeline::make([
        Jobs\CreateDatabase::class,
        Jobs\MigrateDatabase::class,
        Jobs\SeedDatabase::class,          // ← descomentar
    ])->send(...)->shouldBeQueued(false),
],
```

`config/tenancy.php` ya apunta a `Database\Seeders\EmpresaSeeder`, que ahora sí existe.

---

## 4. Poner al día las empresas que ya tienes

Las creadas en la Fase 0 no tienen las tablas nuevas ni los perfiles:

```powershell
php artisan tenants:migrate
php artisan tenants:seed
```

Y crea tu usuario propietario:

```powershell
php artisan climacopos:crear-usuario jectan "Jectan Acosta" --email=jectan@climacoinformatica.com --perfil=propietario --profesional
```

Apunta el PIN y la contraseña que imprime: no se vuelven a mostrar.

Crea también uno en formación, para ver la banda naranja y el bloqueo de medios de pago:

```powershell
php artisan climacopos:crear-usuario jectan "Aprendiz" --perfil=formacion --formacion --profesional
```

---

## 5. Probar

```powershell
php artisan optimize:clear
php artisan test --filter=PermisosUsuarioTest
```

Y en el navegador, `http://jectan.climacopos.test/panel`:

1. Te manda a **vincular**. Email y contraseña del propietario, nombre del terminal.
2. Aparece la **rejilla de usuarios**. Toca tu foto, mete el PIN.
3. Entras al panel provisional, que lista tus permisos.
4. Pulsa **Ajustes** o **Informes**: aunque tengas permiso, pedirá la contraseña. Confírmala y ya no la vuelve a pedir en 15 minutos.
5. Entra con el usuario en formación: banda naranja permanente y sin acceso a ajustes.

---

## 6. Decisiones tomadas

**El PIN no autoenvía al cuarto dígito.** Se admite PIN de 4 a 8, así que hace falta pulsar la tecla de confirmación. Si prefieres PIN fijo de 4 con envío automático, se cambia en un par de líneas de `selector.blade.php`.

**Un usuario desactivado pierde la sesión al instante.** `SesionSalon::usuario()` comprueba el estado en cada petición, no solo al entrar. Si el propietario desactiva a alguien que está trabajando, queda fuera en el siguiente clic. Cuesta una consulta por petición y compensa.

**La reautenticación se pierde al cambiar de usuario.** `SesionSalon::entrar()` borra la marca. Si no, el siguiente empleado heredaría los 15 minutos de contraseña del anterior.

**El token de terminal se guarda hasheado.** En la cookie va el valor en claro; en base de datos solo `sha256`. Un volcado de la base no permite suplantar un equipo.

**Los permisos inventados se descartan solos.** `Perfil::setPermisosAttribute()` filtra contra el catálogo, así que un permiso mal escrito no se guarda en silencio para luego no funcionar nunca.

---

## 7. Pendiente en esta fase

Lo dejo fuera del paquete porque necesita decisiones tuyas:

- **Envío de invitaciones por email**: la tabla `invitaciones` y el modelo están, falta el `Mailable` y la pantalla de aceptación. Requiere configurar el correo saliente (Mailpit en local, y en producción decidir entre SMTP propio, Resend, Postmark...).
- **Pantalla de gestión de usuarios y perfiles**: el CRUD con la matriz de permisos que devuelve `Permisos::catalogo()`, ya preparada para pintarse agrupada.
- **Horarios**: las tablas están, falta la interfaz. Encaja mejor en la Fase 3, junto al motor de huecos que las va a consumir.

---

## 8. Siguiente: Fase 2

Catálogo: familias, servicios y productos con fotos y características, profesionales por servicio, y las plantillas por tipo de negocio que se precargan en el alta.
