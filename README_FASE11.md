# CLIMACO POS — Fase 11 · Correo saliente

Configuración de SMTP desde el panel, plantillas de aviso y recordatorios automáticos.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

Después, en `admin.climacopos.test` → **Correo**, configura el servidor y envía una prueba.

Y al scheduler, para los recordatorios:

```php
Schedule::command('climacopos:recordatorios')->hourly();
```

---

## 2. Qué hay

Seis avisos automáticos al cliente del salón: solicitud recibida, cita confirmada, cita cancelada, recordatorio de la víspera, pago recibido y devolución realizada.

---

## 3. Decisiones

### 3.1 El SMTP se configura en el panel, no en el `.env`

Como las claves de Stripe. La pantalla trae **presets de los proveedores habituales** —Gmail, Microsoft 365, IONOS, Hostinger, Brevo— que rellenan servidor, puerto y cifrado con un clic.

Y **traduce los errores de SMTP**, que son crípticos de serie. En vez de «Connection could not be established with host», se lee «No se pudo conectar. Comprueba el servidor y el puerto». Quien configura esto no tiene por qué saber qué es un handshake TLS.

### 3.2 Cada salón puede usar su propio servidor

Opcional. Un salón con dominio y correo propios prefiere que los avisos salgan desde su dirección: llegan menos a spam y dan mejor imagen que un remitente desconocido. Si no lo configura, se usa el de la plataforma.

### 3.3 El nombre del remitente es siempre el del salón

Aunque el servidor sea el nuestro. Una clienta que reserva en «Peluquería Jectán» espera un correo de la peluquería, no de una marca que no conoce y que probablemente marcará como spam.

Y el `Reply-To` apunta al correo del salón: si la clienta responde, la respuesta llega a quien tiene que llegar.

### 3.4 Un fallo de correo nunca tumba una operación

`GestorCorreos` no lanza excepciones: registra el fallo y devuelve `false`. Si el servidor SMTP está caído, la reserva se crea igual y el ticket se cobra igual. Hay un test que apunta a un servidor inexistente y comprueba que no revienta.

### 3.5 Todos los envíos quedan registrados

No es burocracia: sirve para responder a «no me ha llegado nada» sin adivinar, y para no mandar dos veces el mismo recordatorio. Hay un test específico para eso, porque recibir dos recordatorios de la misma cita queda descuidado.

Los registros se pueden purgar a los seis meses.

### 3.6 El recordatorio se busca por ventana horaria

El comando corre **cada hora**, no una vez al día, y busca las citas que caen en la franja correspondiente a la antelación configurada. Con 24 horas, una cita de las 9:00 avisa a las 9:00 del día anterior, no a medianoche.

Es el correo que más dinero ahorra: un plantón deja un hueco que ya no se vende.

### 3.7 Las plantillas usan tablas y estilos en línea

Feo de escribir, pero es lo que llega bien a Outlook y Gmail. Flexbox y grid se rompen sin aviso en los clientes de correo, y no hay forma de enterarse hasta que un cliente se queja.

---

## 4. Probar

```powershell
php artisan migrate --env=testing --force
php artisan test --filter=CorreosTest
php artisan climacopos:recordatorios --simular
```

Trece pruebas, con `Mail::fake()`: no se envía nada de verdad. Comprueban la selección de servidor, el cifrado de contraseñas, el control de duplicados y que un fallo no lanza excepción.

`--simular` lista a quién se avisaría sin enviar nada. Útil la primera vez.

---

## 5. Pendiente

- **Enganchar los envíos** a los puntos donde toca: crear reserva, confirmar, cancelar y cobrar. La lógica está; falta la llamada, que es una línea en cada sitio. Lo dejo aparte a propósito para que decidas qué avisos quieres activos de salida.
- **Avisos de impago y borrado** a los salones, que es lo que quedaba pendiente de la Fase 9.
- **SMS o WhatsApp** para el recordatorio: se leen mucho más que el correo, pero cuestan dinero por mensaje.
- **Que el salón edite los textos** de sus correos.
- **Baja de comunicaciones** por cliente, que conviene tener por RGPD antes de mandar nada promocional.
