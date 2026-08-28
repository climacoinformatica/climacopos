# CLIMACO POS — Fase 17 · Usuarios y control horario

Gestión de personal desde el panel y registro de jornada conforme a la normativa.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=FichajesTest
```

Ctrl+Shift+R.

---

## 2. Dónde estamos legalmente

El registro de jornada es **obligatorio desde mayo de 2019** (artículo 34.9 del Estatuto de los Trabajadores, tras el RDL 8/2019). Hay que conservarlo **cuatro años** y tenerlo a disposición de los trabajadores, sus representantes y la Inspección de Trabajo.

Además hay un **Real Decreto en tramitación** que hará el registro exclusivamente digital, exigiendo sistemas trazables, inmutables y accesibles en remoto para la Inspección. El Consejo de Ministros aprobó su tramitación urgente en septiembre de 2025; tras el dictamen del Consejo de Estado de marzo, en julio se acordó llevarlo a septiembre. **Todavía no está publicado en el BOE.**

Lo relevante para nosotros: el sistema está diseñado ya con esos requisitos. Cuando salga, tus salones no tendrán que cambiar nada.

Estas fechas se mueven. Conviene confirmarlas antes de comunicárselas a un cliente.

---

## 3. Los fichajes

### 3.1 Tabla inmutable, como VERI\*FACTU

Los fichajes no se editan ni se borran. El modelo lanza excepción si alguien lo intenta. Un registro de jornada que se puede modificar sin dejar rastro **no prueba nada**, y es exactamente lo que el reglamento quiere impedir.

### 3.2 Corregir crea un registro nuevo

El original se marca como anulado con su motivo y quién lo hizo; se crea otro que lo sustituye. La Inspección puede ver los dos. Hay un test que lo comprueba: tras corregir hay **dos** registros en la base, no uno.

### 3.3 Una entrada sin salida no inventa la hora

Se marca el día como incompleto y se suman **cero** minutos. Rellenar la salida con una hora estimada sería falsear el registro, y es justo lo que se sanciona.

### 3.4 Los fichajes manuales se ven

Cuando un responsable añade uno olvidado, queda marcado como `MANUAL` con su motivo. La Inspección mira esto: un registro lleno de fichajes manuales pierde credibilidad, así que la propia pantalla avisa de que debe ser la excepción.

### 3.5 El estado no depende del día

Se mira el último fichaje sin filtrar por fecha. Quien entra a las 22:00 y sale a las 02:00 sigue trabajando pasada la medianoche.

### 3.6 La exportación es entregable

CSV con cabecera completa —empresa, NIF, trabajador, periodo— y las incidencias marcadas. Es lo que hay que entregar a cada persona trabajadora y lo que pediría la Inspección.

---

## 4. Gestión de usuarios

*Ajustes → Usuarios*, o directamente desde el menú.

**Las credenciales se muestran una vez.** El PIN y la contraseña se guardan cifrados, así que si se pierden hay que generar otros. La pantalla lo avisa.

**Dar de baja no borra.** Se desactiva: sus tickets, sus citas y sus fichajes tienen que seguir existiendo, y el registro de jornada hay que conservarlo cuatro años aunque la persona se haya ido.

**No se puede dar de baja al único propietario**, ni darse de baja uno mismo. Son las dos formas de quedarse fuera del propio panel.

**El límite del plan se aplica al dar de alta**, con el mensaje explicando cómo resolverlo. Bajar de plan no desactiva a nadie, como acordamos.

---

## 5. Probar

```powershell
php artisan test --filter=FichajesTest
```

Veinticuatro pruebas. Las que definen el comportamiento: que no se puede entrar dos veces, que la pausa resta, que una entrada sin salida no inventa horas, que un fichaje no se puede editar ni borrar, y que corregir conserva el original.

En pantalla: entra con un usuario, ve a **Fichar**, y prueba la secuencia entrada → pausa → fin de pausa → salida. Después, en el informe mensual, verás el día con sus cuatro marcas.

---

## 6. Pendiente

- **Fichar desde el móvil** con el QR del terminal, para quien no pasa por el mostrador.
- **Avisos de jornada**: quien lleva más de nueve horas dentro, o quien olvidó fichar la salida ayer.
- **Comparar con el horario previsto**, que ya existe desde la Fase 3: detectar retrasos y horas de más.
- **Vacaciones y ausencias**, que van en la misma pantalla y hoy no existen.
- **Acceso del trabajador a su propio registro** sin pasar por el responsable, que la normativa exige.
