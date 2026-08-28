# CLIMACO POS — Fase 8 · Pagos online con Stripe Connect

Fianzas y prepagos en el portal, anticipos que descuentan en el TPV y devoluciones automáticas.

---

## 1. Instalación

### 1.1 Claves de Stripe

Crea una cuenta en Stripe, activa **Connect** en el panel, y añade al `.env`:

```ini
PASARELA_PAGO=stripe

STRIPE_PUBLICA=pk_test_...
STRIPE_SECRETO=sk_test_...
STRIPE_WEBHOOK=whsec_...

# Comisión de la plataforma sobre cada reserva, en %. 0 = no cobramos nada.
COMISION_PLATAFORMA_PCT=0
```

Son las claves de **la plataforma**, no de ningún salón: cada salón conecta su propia cuenta.

### 1.2 Webhook

En Stripe → Desarrolladores → Webhooks, añade el endpoint:

```
https://{salon}.climacopos.com/webhook/stripe
```

Eventos: `checkout.session.completed`, `checkout.session.expired`, `charge.refunded`, `account.updated`.

En local, con Stripe CLI:

```
stripe listen --forward-to http://jectan.climacopos.test/webhook/stripe
```

### 1.3 Lo demás

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

---

## 2. El flujo del dinero

```
Cliente final ──paga──▶ Cuenta Stripe DEL SALÓN
                           │
                           └── application_fee ──▶ Plataforma (si se configura)
```

**El dinero nunca pasa por nuestra cuenta.** Si pasara, estaríamos actuando como entidad de pago, que es una figura regulada por el Banco de España y exige licencia. Con Connect, Stripe abre una cuenta a nombre del salón, el cliente le paga a él, y nosotros —si queremos— cobramos una comisión que Stripe descuenta automáticamente.

Por eso el alta se hace **en Stripe y no en nuestro panel**: piden documentación, titularidad e IBAN, y esos datos no deben pasar por aquí.

---

## 3. Cómo lo usa un salón

1. **Ajustes → Pagos online → Conectar mi cuenta.** Va a Stripe, rellena sus datos y vuelve.
2. Espera a que el estado ponga «Activa». Puede tardar unos minutos o unas horas.
3. En **Catálogo**, abre un servicio y en «Reserva online» elige Fianza o Pago completo.

El pago se configura **por servicio**, no globalmente: lo normal es pedir fianza en lo que ocupa mucha agenda —mechas, alisados— y dejar libre un corte de treinta minutos.

---

## 4. Decisiones

### 4.1 El webhook es la fuente de verdad, no la vuelta del navegador

La URL de retorno se puede escribir a mano, y el cliente puede cerrar la pestaña justo después de pagar. El cobro se da por bueno cuando lo confirma Stripe por webhook; la vuelta del navegador solo consulta el estado para enseñárselo al cliente al momento.

**La firma se verifica siempre.** Sin eso, cualquiera que conociera la URL podría enviar un «pago completado» falso y quedarse con una cita gratis. Se rechazan además los eventos de más de cinco minutos, para que un reenvío no duplique nada.

### 4.2 Devolución automática al rechazar

Si el salón rechaza una reserva, el dinero vuelve solo. Quedarse una fianza de una cita que no se va a prestar no tiene defensa posible, y depender de que alguien se acuerde de devolverla a mano es pedir problemas.

Al cancelar hay matiz: si cancela **el salón** se devuelve siempre —el cliente no ha hecho nada mal—; si cancela **el cliente**, solo dentro del plazo configurado.

### 4.3 La comisión también se devuelve

En el `refund` se envía `refund_application_fee`. Sin ese parámetro, Stripe nos dejaría quedarnos la comisión de un pago devuelto entero.

### 4.4 Los anticipos se aplican solos en el TPV

Al abrir el ticket desde una cita pagada, se añade un cobro con medio `ANTICIPO` por lo ya pagado, y el TPV pide solo la diferencia. Sin esto se le cobraría dos veces al cliente, que es la queja más cara de resolver.

Hay un test que lo comprueba: mechas de 85 € con 20 € de fianza dejan 65 € pendientes en el TPV.

### 4.5 Los céntimos, con `round()`

Stripe trabaja en céntimos enteros. `(int)(22.10 * 100)` da **2209** en PHP por la representación en coma flotante. Ese céntimo perdido acaba siendo una reclamación, así que va con `round()`.

### 4.6 Interfaz de pasarela desde el principio

`Pasarela` es una interfaz aunque solo haya una implementación. Muchos salones ya tienen TPV virtual con su banco y querrán **Redsys**, que además ofrece Bizum nativo —algo que Stripe no da en España—. Con el contrato puesto, añadirlo es escribir una clase, no reescribir el portal.

### 4.7 Sin SDK de Stripe

Se habla con la API por HTTP. Son cuatro llamadas contra una API muy estable, y así el despliegue sigue siendo copiar ficheros, sin `composer require` ni una dependencia más que actualizar.

---

## 5. Probar

```powershell
php artisan test --filter=PagosTest
```

Diecinueve pruebas. Ninguna llama a Stripe: se comprueba la lógica propia —importes de fianza, anticipos, plazos, estados— y la verificación de firma del webhook, que es lo que puede romperse por nuestra parte.

Para probar de verdad hacen falta las claves de prueba de Stripe. La tarjeta `4242 4242 4242 4242` con cualquier fecha futura y cualquier CVC simula un pago correcto.

---

## 6. Pendiente

- **Redsys**: la interfaz está, falta la implementación. Es la que pedirán los salones que ya tienen TPV virtual.
- **Bizum**: llega con Redsys.
- **Emails**: recibo del pago y aviso de devolución.
- **Preautorización** en lugar de cobro: retener el importe y capturarlo solo si hay plantón. Más elegante que cobrar y devolver, pero da más problemas de soporte.
- **Panel de la plataforma** con las comisiones cobradas a todos los salones.

---

## 7. Siguiente: Fase 9

Suscripciones: planes, prueba de 14 días, cobro mensual a los salones, límites por plan y el panel de superadministración en `admin.climacopos.com`.
