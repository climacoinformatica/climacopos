# CLIMACO POS — Fase 3 · Agenda y motor de huecos

Horarios, ausencias, bloqueos, calendario multi-profesional y el cálculo de disponibilidad que alimentará el portal de reservas.

Descomprime encima de `C:\xampp\htdocs\climacopos`, sobrescribiendo.

---

## 1. Instalación

```powershell
cd C:\xampp\htdocs\climacopos
php artisan tenants:migrate
php artisan tenants:seed
php artisan optimize:clear
```

### Cableado manual: registrar el fichero de ayudantes

En `composer.json`, dentro de `autoload`, añade el bloque `files`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/"
    },
    "files": [
        "app/Support/ayudantes.php"
    ]
},
```

Y después:

```powershell
composer dump-autoload
```

Sin esto, `config_empresa()` no existe y la agenda falla al arrancar.

### Configurar horarios

La agenda sale vacía hasta que alguien tenga horario. Entra en **Horarios** y rellena la semana de cada profesional. Si no hay ningún usuario marcado como profesional:

```powershell
php artisan climacopos:crear-usuario jectan "Marta" --perfil=profesional --profesional
```

---

## 2. El motor de huecos

Es la pieza central del producto. `App\Services\MotorHuecos` responde a una pregunta aparentemente simple —¿a qué horas puede reservar este cliente?— cruzando siete cosas a la vez:

1. Horario del profesional para ese día de la semana
2. Excepciones: vacaciones, bajas, festivos, cierres, horarios especiales
3. Citas existentes, respetando la **pausa** intermedia
4. Bloqueos manuales de la agenda
5. Retenciones temporales de clientes que están pagando en ese momento
6. Recursos físicos limitados (cabinas, lavacabezas)
7. Antelación mínima y máxima configuradas

### 2.1 La pausa es el punto que da valor

Un tinte son 20 minutos aplicando, 30 de espera y 15 de lavado: 65 minutos en la agenda, pero el profesional solo está atado 35.

```
09:00 ████████ aplicando (20')
09:20 ░░░░░░░░ LIBRE — puede atender a otra clienta (30')
09:50 ██████   lavado y secado (15')
10:05 fin
```

El motor calcula los **tramos activos** y solo bloquea esos. En la agenda, la pausa se dibuja rayada.

Un salón que trabaje con esto puede vender un corte de 30 minutos dentro de la espera de un tinte. Sin ello, la hora entera queda muerta. Con cuatro tintes al día son dos horas de facturación perdidas.

Cuidado con la asimetría: la **cabina sí sigue ocupada** durante la pausa, porque la clienta no se levanta de ella aunque la profesional se haya ido. Hay un test que lo comprueba.

### 2.2 Trabajo en minutos, no en fechas

Todo el motor usa enteros desde medianoche: `540` es las 09:00. `App\Support\Intervalo` encapsula la aritmética. Comparar solapes es una resta, no una comparación de objetos de fecha, y desaparecen de raíz los problemas de zona horaria dentro de un mismo día.

Detalle importante: **tocarse en el extremo no es solapar**. Una cita que acaba a las 10:00 y otra que empieza a las 10:00 conviven. Si se implementa con `<=` en vez de `<`, se pierde un hueco entre cada dos citas.

---

## 3. Decisiones de diseño

### 3.1 Las reservas pendientes ocupan sitio

Mientras el propietario decide si acepta una reserva online, el hueco queda retenido. Si no fuera así, dos clientes podrían reservar la misma hora mientras la primera espera confirmación, y alguien se llevaría un disgusto.

En la agenda se dibujan con borde discontinuo y la etiqueta «Sin confirmar».

### 3.2 Las líneas guardan copia de los tiempos

`reserva_lineas` copia `duracion_min`, `tiempo_pausa_min` y `tiempo_final_min` del artículo en el momento de reservar. Si mañana el salón decide que el corte pasa de 30 a 45 minutos, las citas ya cerradas conservan su duración y la agenda no se descoloca sola.

Lo mismo con `nombre_servicio`: si el artículo se borra, la cita antigua sigue siendo legible.

### 3.3 El teléfono identifica al cliente

`Cliente::porTelefono()` normaliza espacios, guiones y prefijos antes de comparar, así que «600 123 456» y «600123456» son la misma persona. En un salón mucha gente no da email, pero todo el mundo da su móvil.

### 3.4 Código de reserva legible por teléfono

`RS-8F3K2`, sin las letras I y O ni los números 0 y 1, que se confunden al dictarlos. Es el código que la clienta lee cuando llama para cambiar la cita.

### 3.5 La ocupación no cuenta la pausa

El porcentaje que aparece bajo cada profesional mide el tiempo **activo** vendido sobre las horas disponibles. Si contara la pausa, un salón lleno de tintes parecería estar al 100% teniendo huecos vendibles.

---

## 4. Pruebas

```powershell
php artisan test --filter=MotorHuecosTest
```

Treinta y una pruebas. Las que más importan:

- Durante la pausa se puede meter otro servicio, pero solo si cabe.
- La cabina sigue ocupada durante la pausa aunque el profesional se libere.
- Tocarse en el extremo no es solapar.
- Las reservas pendientes retienen el hueco; las canceladas lo liberan.
- Un festivo sin profesional afecta a todo el salón.
- Un horario especial sustituye al habitual en lugar de sumarse.
- Mover una cita no choca consigo misma.
- El mismo teléfono con distinto formato reutiliza la ficha del cliente.

---

## 5. Cómo usarla

**Ver el día**: Agenda muestra una columna por profesional, con las horas a la izquierda y el porcentaje de ocupación bajo cada nombre. Las zonas rayadas oscuras son fuera de horario.

**Crear una cita**: clic en cualquier hueco libre de la columna del profesional. Se abre el formulario con esa hora y ese profesional ya puestos. También desde «Nueva cita».

**Encadenar servicios**: en el formulario, «Añadir servicio». Cada uno puede ir con un profesional distinto — corte con Marta y después color con Ana. El siguiente empieza cuando el anterior termina del todo.

**Buscar cliente**: escribe dos letras del nombre o del teléfono. Si el cliente acumula plantones, sale avisado en el desplegable.

**Confirmar reservas online**: entra en la cita pendiente y confirma o rechaza. El rechazo pide motivo.

---

## 6. Pendiente

- **Arrastrar y soltar** para mover citas. La ruta y el servicio están (`agenda.cita.mover`), falta el JavaScript. En escritorio es cómodo; en tablet táctil sobre una rejilla densa suele ser un desastre, así que lo pensaré con calma.
- **Vista semanal** de un solo profesional.
- **Jornada partida** desde la pantalla de horarios: ahora hay que darla de alta como horario especial. La tabla admite varios tramos por día, es solo interfaz.
- **Recordatorios** 24 h antes: necesita el correo configurado.
- **Auto-rechazo** de reservas pendientes caducadas: el ajuste `caducidad_pendiente_horas` existe, falta el comando programado.

---

## 7. Siguiente: Fase 4

El portal público de reservas en `{slug}.climacopos.com`: catálogo con fotos, selección de profesional, calendario de huecos consumiendo este motor, alta de cliente con RGPD y el aviso destellante en el panel cuando entra una reserva nueva.
