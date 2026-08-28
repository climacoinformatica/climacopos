# CLIMACO POS — Fase 22 · Alta automática de salones

El cliente elige su dirección, se le crea la base de datos y entra a configurarla.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=AltaSalonTest
```

**Requiere añadir rutas y un middleware a mano**: está todo en `routes/rutas_a_anadir.md`.

---

## 2. El problema de fondo

`CREATE DATABASE` **no participa en transacciones**. Si el alta falla a mitad, la base ya existe y una transacción de Laravel no la deshace.

Por eso `GestorAltas` limpia a mano: si algo revienta después de crear el tenant, se borra el tenant y su base antes de propagar el error. Un salón a medio crear es peor que ninguno, porque el cliente ve su subdominio ocupado y **no puede volver a intentarlo con el mismo nombre**.

El mensaje de error lo dice explícitamente: «no se ha guardado nada a medias, puedes volver a intentarlo». Es información que el cliente necesita para no llamarte.

---

## 3. Decisiones

### 3.1 El subdominio no se puede cambiar

Y el formulario lo avisa antes, en negrita. Cambiarlo después implicaría migrar la base, actualizar el dominio y romper todos los enlaces que la clienta tenga guardados. Es de las pocas cosas verdaderamente irreversibles del sistema, y hay que decirlo antes, no después.

### 3.2 Lista de subdominios reservados

Unos son nuestros —`admin`, `api`— y otros romperían cosas: `www` y `mail` los usan los clientes de correo. Si alguien pide uno reservado, **se le propone una alternativa libre**: dejarlo en un callejón sin salida es la forma más rápida de perder un alta.

### 3.3 El subdominio se normaliza mientras se escribe

El JavaScript quita tildes, pasa a minúsculas y sustituye lo que no vale. Nadie tiene por qué saber las reglas de los nombres de dominio, y explicárselas en un mensaje de error es peor que arreglarlo solo.

### 3.4 Las credenciales viajan en la sesión, no en la URL

Una URL queda en el historial del navegador, en los registros del servidor y en cualquier «enviar enlace». El PIN de acceso al TPV no debe acabar ahí.

Se muestran **una vez**, con el aviso de que se guardan cifradas y no hay forma de recuperarlas.

### 3.5 Correo verificado antes de crear nada

Cada alta provisiona una base de datos. Sin verificación, un robot podría llenarte el disco en una tarde.

### 3.6 Un salón por cuenta, de momento

Nada lo impide técnicamente, pero abrir esa puerta sin haber pensado la facturación de cadenas complica más de lo que resuelve ahora.

### 3.7 El asistente es obligatorio, pero se puede saltar

Un salón sin datos fiscales no puede emitir una factura y sin horario la agenda sale vacía. El middleware redirige al asistente hasta terminarlo.

Pero hay un enlace discreto de «lo configuro más tarde». Obligar a rellenar cuatro formularios a alguien que solo quiere ver el programa es la forma más rápida de que no vuelva.

---

## 4. El asistente

Cuatro pasos, con lo mínimo:

**Datos fiscales.** Con un aviso de que deben coincidir con el alta censal: mejor comprobarlo ahora que tras emitir cien tickets.

**Horario.** Días marcados por defecto de martes a sábado, que es lo habitual en peluquería. Un solo tramo; los partidos se ajustan después.

**Servicios.** Precargado con tres ejemplos realistas que puede editar o borrar. Una pantalla vacía con un botón de «añadir» invita a saltarse el paso.

**Listo**, con la lista de lo que puede hacer después sin prisa.

---

## 5. Probar

```powershell
php artisan test --filter=AltaSalonTest
```

Quince pruebas. Las que definen el comportamiento: que un subdominio reservado no llega a crear nada, que el salón nace sin configurar, que las credenciales se devuelven en claro una vez pero se guardan cifradas, y que un duplicado se rechaza.

**Ojo:** estas pruebas crean bases de datos reales y las borran al terminar. Si alguna falla a mitad puede dejar una `climacopos_emp_N` huérfana. El script `preparar_pruebas.ps1` las limpia.

En pantalla, el recorrido completo: crea una cuenta en la web, verifica el correo, entra en tu área y pulsa «Crear mi salón».

---

## 6. Pendiente

- **Correo de bienvenida** con la dirección del salón, para que no dependa de haber apuntado el PIN.
- **Cola de trabajos**: ahora el alta corre en la misma petición y tarda unos segundos. Con muchos altas simultáneos convendría pasarlo a cola con una pantalla de espera.
- **Paso de equipo** en el asistente: añadir profesionales. Lo quité para no alargarlo, pero es lo primero que hará falta.
- **Avisar al superadministrador** de cada alta nueva.
- **Baja de salón** desde el área de cliente, con exportación previa de sus datos. Es un derecho RGPD y hoy no existe.
