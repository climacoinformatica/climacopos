# CLIMACO POS — Fase 12 · Enganches y límites

Los correos conectados donde tocaba, avisos de impago a los salones y los límites de plan aplicados de verdad.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=LimitesYAvisosTest
```

---

## 2. Correos enganchados

| Cuándo | Qué se manda |
|---|---|
| Reserva online creada, pendiente | Acuse de recibo |
| Reserva creada ya confirmada | Confirmación |
| Se confirma una reserva | Confirmación |
| Se cancela o rechaza | Aviso con el motivo |
| Se cobra un pago online | Recibo |
| Se devuelve un pago | Aviso de devolución |
| La víspera de la cita | Recordatorio |

Cada salón decide cuáles quiere con los interruptores `avisar_reserva`, `avisar_recordatorio` y `avisar_cancelacion`.

**Una distinción que importa:** si la reserva nace pendiente, se manda un acuse de recibo, no una confirmación. Recibir «cita confirmada» cuando todavía no lo está genera plantones y discusiones en el mostrador.

---

## 3. Avisos de la plataforma al salón

Cuatro correos, con el tono calibrado a cada situación:

**Primer impago.** Tono tranquilo, porque casi siempre es una tarjeta caducada. Deja claro en un recuadro verde que todo sigue funcionando.

**Segundo impago.** Aquí sí urge, pero explicando que hay margen hasta la madrugada y qué se podrá seguir haciendo después.

**Aviso de borrado**, quince días antes, ofreciendo enviarles una copia de sus datos.

**Fin de la prueba**, a siete días y a uno. Dos avisos bastan: más resulta insistente y menos se pierde entre el resto del correo.

### 3.1 Estos correos nunca salen por el servidor del salón

`CorreosPlataforma` sale del contexto del tenant antes de preparar el envío. Si no lo hiciera y el salón tuviera SMTP propio, el aviso de impago saldría desde el servidor del propio moroso. Hay un test que lo comprueba.

---

## 4. Límites por plan

Los planes definían límites desde la Fase 0 pero nadie los comprobaba. Ahora `App\Support\LimitesPlan` los centraliza.

**Los mensajes dicen qué hacer, no solo que no se puede:** «Tu plan Básico permite 2 profesionales. Para añadir más, cambia de plan desde Suscripción». Un «límite alcanzado» a secas obliga al usuario a adivinar.

**Cero significa sin límite**, no que no caben.

**Bajar de plan no desactiva a nadie.** Si un salón con cinco profesionales baja a un plan de tres, no se le desactivan dos por sorpresa: eso dejaría citas huérfanas y horarios rotos. Se le impide añadir más y ya irá ajustando. Hay un test para esto.

**Recepción no ocupa plaza de profesional.** Solo cuentan los usuarios marcados como profesionales, que son los que atienden clientas.

---

## 5. Probar

```powershell
php artisan test --filter=LimitesYAvisosTest
```

Catorce pruebas. Las más útiles son las de comportamiento: que bajar de plan no desactiva a nadie, que los avisos de impago no salen del servidor del moroso, y que los mensajes de límite explican cómo resolverlo.

---

## 6. Pendiente

- **Aplicar `LimitesPlan` en los controladores** de alta de usuario y vinculación de terminal. El servicio está listo; falta la llamada, que es una línea en cada sitio.
- **Facturas rectificativas R1–R5** en VERI\*FACTU.
- **Impersonación** para dar soporte.
- **Logotipo monocromo** de 384 px para la impresora térmica, y la versión horizontal con transparencia para cabeceras.
