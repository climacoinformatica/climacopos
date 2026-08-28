# CLIMACO POS — Fase 9 · Suscripciones y morosidad

Planes, cobro mensual a los salones, ciclo de impagos y gestión desde el panel de administración.

---

## 1. Instalación

### 1.1 Registrar el middleware

En `bootstrap/app.php`, junto a los alias que ya tienes:

```php
'suscripcion' => \App\Http\Middleware\ComprobarSuscripcion::class,
```

### 1.2 Lo demás

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

### 1.3 Precios en Stripe

Los planes de la base de datos necesitan su equivalente en Stripe. En el panel de Stripe, **Productos → Añadir producto**, crea uno por plan con precio recurrente mensual y anual, y copia los identificadores (`price_...`) a las columnas `stripe_price_mes` y `stripe_price_ano` de la tabla `planes`.

### 1.4 Webhook de facturación

Es **distinto** del de las reservas: va al dominio central.

```
https://admin.climacopos.com/webhook/billing
```

Eventos: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`.

### 1.5 Tarea diaria

```powershell
php artisan climacopos:mantenimiento-suscripciones
```

En producción, al scheduler: `->dailyAt('04:15')`.

---

## 2. El ciclo de morosidad

```
  Pago correcto ──────────────────────▶ ACTIVA

  1er impago ─────────────────────────▶ MOROSA
                                        Todo funciona. Banda naranja en el panel.

  2º impago ──────────────────────────▶ SUSPENDIDA
                                        Solo lectura. Sin informes.
                                        Entra a las 4:00 de la madrugada siguiente.

  +90 días ───────────────────────────▶ Borrado, tras avisar 15 días antes.
```

### 2.1 El primer impago no corta nada

Una tarjeta caducada es lo más común del mundo y no significa que el cliente no quiera pagar. Cortar a la primera espanta a salones que habrían pagado al día siguiente. Se avisa con una banda naranja permanente en el panel y un enlace directo a cambiar la tarjeta.

### 2.2 La suspensión nunca entra en mitad de la jornada

Cuando llega el segundo impago, la cuenta pasa a suspendida **pero la restricción se programa para las 4:00 de la madrugada siguiente**. Bloquear un TPV con clientas esperando en el salón pierde al cliente para siempre, aunque pague al día siguiente.

Durante esas horas el panel avisa: «tu cuenta pasará a solo lectura esta noche».

### 2.3 Solo lectura significa poder atender, no poder exportar

Un salón suspendido **puede**: entrar, ver su agenda, consultar clientes y fichas técnicas. Es lo justo para atender a quien ya tenía cita cerrada.

**No puede**: vender, cobrar, crear reservas, editar el catálogo ni **sacar informes**.

Los informes se cortan a propósito aunque sean de solo lectura: es lo único que un salón suspendido podría aprovechar de verdad, exportar toda su información y marcharse sin pagar. Consultar la agenda para no dejar tirada a una clienta, en cambio, no perjudica a nadie.

### 2.4 El borrado no es automático

A los 90 días el comando **lista** las empresas que cumplen el plazo, pero no las borra: hay que confirmarlo con `climacopos:purgar-empresa`. Destruir los datos de un cliente sin intervención humana es un riesgo que no compensa automatizar, y un fallo ahí no tiene vuelta atrás.

Quince días antes se marca el aviso, para engancharle el email cuando esté el correo saliente.

---

## 3. Para el salón

*Panel → Suscripción* (solo con permiso `empresa.facturacion`, que de fábrica tiene el propietario):

- Estado y días de prueba restantes.
- Comparativa de planes con precio mensual y anual, indicando cuánto se ahorra al año.
- Botón al **portal de Stripe** para cambiar tarjeta, plan o cancelar. Lo gestiona Stripe para no guardar datos de tarjeta en ningún momento.
- Sus facturas, con enlace al PDF de Stripe.

---

## 4. Para ti

*Administración → Empresas → una empresa*: además de los contadores, un bloque para **cambiar el estado a mano** con motivo obligatorio, que queda en el log.

Sirve para lo que pasa de verdad: dar una cortesía, reactivar a alguien que pagó por transferencia, o ampliar una prueba a un salón que está a punto de decidirse.

---

## 5. Probar

```powershell
php artisan test --filter=SuscripcionesTest
```

Catorce pruebas. Las que definen el comportamiento acordado:

- Un impago deja la cuenta operativa.
- Dos la suspenden, pero a las 4:00 del día siguiente, no al instante.
- Suspendida no puede escribir ni ver informes.
- Pagar borra el historial y devuelve todo a la normalidad.
- Una factura reenviada por Stripe no se duplica.

---

## 6. Pendiente

- **Emails**: aviso de impago, de suspensión y de borrado. Falta configurar el correo saliente, que también debería poder hacerse desde el panel de administración y no desde el `.env`.
- **Límites por plan**: la tabla los tiene (profesionales, terminales, almacenamiento) pero todavía no se aplican al crear usuarios.
- **Impersonación** para dar soporte, con registro en auditoría.
- **Métricas de plataforma**: facturación recurrente, altas y bajas.
- **Prorrateo** al cambiar de plan a mitad de periodo. Stripe lo hace solo, pero conviene enseñárselo al salón antes de confirmar.

---

## 7. Siguiente: Fase 10

VERI\*FACTU: cadena de hashes SHA-256 por empresa y serie, XML validado contra el XSD de la AEAT, envío por SOAP, QR en el ticket y libro registro. Es la fase con más peso legal del proyecto, y hay una decisión que conviene consultar antes con un asesor fiscal: con qué certificado se firma y se envía.
