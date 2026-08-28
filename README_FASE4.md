# CLIMACO POS — Fase 4 · Portal público y aviso destellante

Reservas online en `{slug}.climacopos.com`, aviso destellante en el panel y ajustes de reservas.

---

## 1. Instalación

```powershell
cd C:\xampp\htdocs\climacopos
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

Y en el navegador, **Ctrl+Shift+R** la primera vez: hay CSS y JavaScript nuevos.

- Portal: `http://jectan.climacopos.test`
- Panel: `http://jectan.climacopos.test/panel`

---

## 2. El portal público

Cuatro pasos, todos renderizados en servidor:

```
1. ¿Qué te apetece hoy?   → catálogo con fotos, duración y precio
2. ¿Con quién y cuándo?   → profesional, 14 días, huecos en mañana/tarde
3. Tus datos              → nombre, teléfono, RGPD  (hueco retenido 15 min)
4. Tu cita                → código RS-XXXXX, opción de cancelar
```

**Sin framework de JavaScript, a propósito.** Un portal de reservas tiene que funcionar en el móvil viejo de una clienta con mala cobertura en el Valle de Aridane. Cada kilobyte de JS es una reserva menos. El único JavaScript del portal es la cuenta atrás de la retención, y si falla no impide reservar.

**Diseño en claro**, al contrario que el panel. El panel lo mira el equipo ocho horas al día y el oscuro cansa menos; el portal lo abre una clienta treinta segundos, a menudo en la calle con sol, y el claro se lee mejor.

**Detalles pensados para móvil**: los campos usan `font-size: 1rem`, porque por debajo de 16 px iOS hace zoom automático al enfocar y descoloca la página. El teléfono usa `inputmode="tel"` para que salga el teclado numérico. Los días y profesionales van en carruseles horizontales en lugar de listas verticales.

**La retención del hueco.** Al llegar al paso 3 se crea una `reserva_temporal` de 15 minutos. Sin eso, dos personas pueden estar rellenando sus datos para la misma hora y la segunda se lleva un chasco después de escribirlo todo. La cuenta atrás se ve en pantalla y avisa cuando quedan menos de dos minutos.

---

## 3. El aviso destellante

Barra fija en la parte superior del panel, presente en todas las pantallas.

**Solo destella cuando hay reservas sin confirmar.** Los avisos informativos (stock bajo, una cancelación) ponen la barra en gris con el contador, pero sin animación. Reservar el destello para lo que exige acción evita que el equipo se acostumbre a ignorarlo.

**No se apaga leyéndolo.** Un aviso de reserva pendiente sigue activo hasta que la reserva se confirma o se rechaza. Si bastara un clic para silenciarlo, alguien lo apagaría sin querer y dejaría clientes esperando. Los informativos sí se cierran con «Entendido».

**Sondeo cada 10 segundos, no WebSocket.** La red de un salón se corta a menudo, y una conexión persistente que cae deja de avisar sin que nadie se entere. Un sondeo que falla vuelve a intentarlo diez segundos después. Además, el endpoint devuelve solo un contador y una huella; el detalle se pide únicamente cuando la huella cambia.

**El sonido se desbloquea con el primer toque.** iOS no reproduce audio hasta que ha habido interacción del usuario. El pitido se genera con la Web Audio API, sin fichero que descargar, y suena solo en el primer aviso, no en cada sondeo.

**El contador va también en el título de la pestaña.** Si el TPV está en otra ventana, se ve el «(2)» en la pestaña del navegador.

**Confirmar sin cambiar de pantalla.** El panel lateral trae los botones de Confirmar y Rechazar. Si estás en la agenda, se recarga sola para que veas la cita ya colocada.

---

## 4. Ajustes de reservas

*Panel → Ajustes* (pide contraseña, como todo lo sensible):

| Ajuste | Para qué |
|---|---|
| Confirmación automática | Sin marcar, todo pasa por revisión |
| Caducidad de pendientes | Auto-rechazo si nadie decide en X horas |
| Antelación mínima | No reservar con menos de X horas |
| Antelación máxima | Hasta cuándo se puede reservar |
| Cancelar hasta | Plazo para que el cliente cancele solo |
| Plantones para exigir pago | Se usará con la pasarela, Fase 8 |
| Horas de la agenda | Solo afecta a lo que se dibuja |

Abajo está el **enlace del portal** con botón de copiar. Es el que se pega en la biografía de Instagram y en Google, y por donde entrarán las reservas.

La antelación mínima **no se aplica desde el panel**: el salón puede meter una cita para dentro de diez minutos si le da la gana. Solo limita al cliente.

---

## 5. Mantenimiento automático

```powershell
php artisan climacopos:mantenimiento-agenda
```

Recorre todas las empresas, purga retenciones caducadas y auto-rechaza las reservas pendientes que nadie ha atendido en el plazo configurado.

En producción irá al scheduler:

```php
Schedule::command('climacopos:mantenimiento-agenda')->hourly();
```

En local, si quieres verlo funcionar, ejecuta `php artisan schedule:work` en una consola aparte.

---

## 6. Ficheros

```
app/Models/Aviso.php
app/Services/GestorReservas.php                    SUSTITUYE (genera avisos)
app/Http/Controllers/Portal/ReservaPortalController.php
app/Http/Controllers/Panel/AvisoController.php
app/Http/Controllers/Panel/AjustesController.php
app/Console/Commands/MantenimientoAgenda.php
database/migrations/tenant/2026_01_05_100001_create_avisos_table.php
resources/views/portal/*.blade.php                 base, servicios, hueco, datos, reserva
resources/views/panel/app.blade.php                SUSTITUYE (barra de avisos)
resources/views/panel/ajustes/reservas.blade.php
public/css/portal.css
public/css/avisos.css
public/js/avisos.js
routes/tenant.php                                  SUSTITUYE
tests/Feature/PortalReservasTest.php
herramientas/actualizar.ps1                        SUSTITUYE
```

---

## 7. Probar

```powershell
php artisan test --filter=PortalReservasTest
```

Y a mano, que es donde se ve:

1. Abre el **panel** en una ventana y el **portal** en otra (o en el móvil, si estás en la misma red).
2. Reserva desde el portal.
3. En menos de diez segundos, la barra roja del panel empieza a parpadear con el contador.
4. Púlsala: se abre el panel lateral con la reserva y los botones.
5. Confirma. El destello se apaga y la cita aparece en la agenda.

Prueba también a **no** hacer nada: el hueco reservado no se ofrece a nadie más mientras la reserva está pendiente.

---

## 8. Pendiente

- **Emails**: confirmación al cliente, aviso de rechazo, recordatorio 24 h antes. Necesita decidir el correo saliente. En local, Mailpit.
- **Pagos y fianzas**: Fase 8, con Stripe Connect.
- **Añadir al calendario** (.ics) en la pantalla de la cita.
- **Varios servicios en una misma reserva online**: ahora el portal permite uno por reserva; el panel ya encadena varios.
- **Página del profesional** en el portal, con su foto y sus servicios.

---

## 9. Siguiente: Fase 5

El punto de venta: rejilla táctil de familias y artículos, cobro multi-medio, cita → ticket desde la agenda, cierre de jornada y arqueo. Ahí entra también el empleado en formación, que ya está preparado en el modelo desde la Fase 1.
