# CLIMACO POS — Fase 5 · Punto de venta, caja y formación

TPV táctil, cobro multi-medio, cita → ticket, cierre de jornada con arqueo, y el empleado en formación completo.

---

## 1. Instalación

```powershell
cd C:\xampp\htdocs\climacopos
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

Ctrl+Shift+R en el navegador: hay CSS y JavaScript nuevos.

---

## 2. El empleado en formación

Es la parte que más cuidado ha llevado, porque en el POS de hostelería fue donde apareció el fallo más molesto: un filtro olvidado en una consulta suelta metió tickets de prácticas en un informe real.

### 2.1 Cómo se garantiza ahora

**Global scope, no un `where` que haya que recordar.** `App\Models\Scopes\ExcluirFormacion` se aplica a todas las consultas del modelo `Ticket`. Para ver los documentos de prácticas hay que pedirlo de forma explícita:

```php
Ticket::count()                   // solo reales
Ticket::conFormacion()->count()   // reales + prácticas
Ticket::soloFormacion()->count()  // solo prácticas
```

Olvidarse del filtro ya no es posible: el comportamiento por defecto es el seguro.

**Serie propia con contador independiente.** Los reales van en la serie `A`, las prácticas en `FOR`. Hay un test que lo comprueba: dos tickets reales, tres de prácticas por medio, y el siguiente real sigue siendo el número 3. Las prácticas no consumen numeración fiscal.

**Solo efectivo, comprobado en el servidor.** `GestorTickets::cobrar()` rechaza cualquier medio que no sea efectivo si el usuario está en formación. No basta con ocultar los botones: alguien con las herramientas del navegador podría enviar la petición a mano.

**Fuera del cierre y del arqueo.** El cierre usa las consultas normales, que ya excluyen formación. Hay un test que vende 22 € reales y 44 € en prácticas y verifica que el arqueo espera exactamente 22 €. Los documentos de prácticas ni siquiera se marcan como cerrados: quedan siempre pendientes, porque nunca entran.

### 2.2 El fichero de consulta

*Caja → Documentos de formación*. Filtro por fechas y empleado, exportación a JSON antes de borrar, y borrado individual, por rango o total. El borrado exige el permiso `formacion.borrar` (solo el propietario de fábrica) y queda registrado en la auditoría.

---

## 3. El TPV

**Rejilla táctil** de familias y artículos, con la foto de fondo atenuada para que el nombre se lea. Pestañas de Servicios y Productos, más una de **Citas de hoy**: al tocar una cita se abre el ticket ya cargado con sus servicios y sus profesionales, y al cobrarlo la cita queda marcada como atendida.

**Cobro multi-medio.** Se puede cobrar 10 € en efectivo y el resto con tarjeta; el ticket se cierra solo cuando el pendiente llega a cero. En efectivo hay botones de importes redondos y el cambio se calcula mientras escribes.

**Cada línea guarda su profesional ejecutor**, que es la base de las comisiones.

**Las invitaciones** dejan la línea a cero con motivo obligatorio y salen en informe aparte, no como descuento comercial.

**El stock se descuenta al cobrar** y se devuelve al anular.

---

## 4. Caja y cierre

El arqueo cruza fondo inicial + ventas en efectivo + entradas − salidas. Los cobros con tarjeta o Bizum no tocan el efectivo teórico, que es lo que a veces se olvida y genera descuadres fantasma.

Al escribir el efectivo contado, la pantalla dice al momento si cuadra, sobra o falta.

**Un ticket incluido en un cierre ya no se puede anular.** Si se pudiera, el cierre dejaría de cuadrar retroactivamente y el libro de facturas de VERI\*FACTU se rompería.

---

## 5. Probar

```powershell
php artisan test --filter=TpvTest
```

Veintitrés pruebas. Las de formación son las que más importan:

- El aprendiz emite en serie `FOR` y no consume numeración fiscal.
- No puede cobrar con tarjeta; sí en efectivo.
- Sus tickets son invisibles por defecto.
- 44 € de prácticas no aparecen en un arqueo de 22 €.
- Borrar las prácticas no toca los tickets reales.

A mano, crea un usuario en formación y entra con su PIN:

```powershell
php artisan climacopos:crear-usuario jectan "Aprendiz" --perfil=formacion --formacion --profesional
```

Verás la banda naranja arriba, en el modal de cobro solo aparecerá Efectivo, y el documento saldrá como `FOR-000001`.

---

## 6. Pendiente

- **Impresión del ticket**: es la Fase 6, con el agente local y el diseñador.
- **Reimpresión y devoluciones parciales**.
- **Bonos y monedero**: los medios de pago existen, falta la lógica de saldo.
- **Anticipos de reservas con fianza**: entra con la pasarela, Fase 8.
- **Búsqueda de cliente desde el TPV**: la ruta está (`tpv.cliente`), falta el buscador en pantalla.

---

## 7. Siguiente: Fase 6

El Agente CLIMACO: cola de impresión, ESC/POS por socket, cajón portamonedas, visor de cliente, configuración por terminal y el diseñador de ticket con cabecera, pie y logotipo.
