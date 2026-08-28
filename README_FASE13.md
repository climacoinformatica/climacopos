# CLIMACO POS — Fase 13 · Devoluciones y facturas rectificativas

La vía correcta para devolver algo de un ticket que ya entró en un cierre.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=DevolucionesTest
```

---

## 2. Anular o rectificar

| Situación | Qué se hace |
|---|---|
| Ticket abierto | Quitar las líneas |
| Ticket cobrado, sin cerrar el día | **Anular** |
| Ticket ya incluido en un cierre | **Rectificar** |

La distinción no es burocracia. **Anular un ticket cerrado descuadraría un arqueo que ya se dio por bueno**, y dejaría en la cadena de VERI\*FACTU una factura que declaró un importe y ahora dice otro.

La rectificativa es un documento **nuevo**, con su propio número, que corrige al anterior sin tocarlo. Igual que en contabilidad no se borra un apunte: se contra-asienta.

---

## 3. Decisiones

### 3.1 Serie propia `R`

Las rectificativas no consumen numeración de facturas. Hay un test: tras devolver, la siguiente venta sigue siendo `A-000002`.

### 3.2 Importes en negativo

Es lo que hace que resten solos en informes, cierres y libro de facturas, sin tratarlos como caso especial en cada consulta.

### 3.3 Tipo R5 ante la AEAT

Un ticket de peluquería es una **factura simplificada**, así que su rectificación es de tipo `R5`. En el XML se declara además `TipoRectificativa = I` (por diferencias): el documento declara solo lo que se corrige, no repite la factura entera. Es lo que encaja con una devolución parcial.

El bloque `FacturasRectificadas` referencia la factura original con su número y fecha, que es lo que la Agencia exige para poder cruzarlas.

### 3.4 Devolución parcial con control de saldo

Se puede devolver una unidad hoy y otra la semana que viene, pero **nunca más de lo vendido**. El servicio descuenta lo ya devuelto en rectificativas anteriores. Sin esa comprobación se podría devolver dos veces el mismo servicio, y hay un test para cada caso.

### 3.5 El medio de devolución puede ser distinto

Se puede haber cobrado con tarjeta y devolver en efectivo. Lo que no se puede es dejarlo sin registrar, porque entonces el arqueo no cuadra.

### 3.6 La formación no se rectifica

Un documento de prácticas no es una factura. Si hay que eliminarlo, se borra desde el fichero de formación. El servicio lo rechaza con ese mensaje.

---

## 4. En pantalla

*TPV → Tickets → Devolver*. Tabla con lo vendido, lo ya devuelto y cuánto queda; el total se calcula mientras se teclea. Botón de «devolver todo» para el caso más común.

En el listado, los tickets con devoluciones muestran cuánto se devolvió, y las rectificativas indican a qué factura corrigen.

---

## 5. Probar

```powershell
php artisan test --filter=DevolucionesTest
```

Veinte pruebas. Las que más importan: que el original no se toca, que no se puede devolver dos veces lo mismo, que el stock se repone, y que la rectificativa encadena en VERI\*FACTU como cualquier otro documento.

---

## 6. Pendiente

- **Devolución del cobro online**: si se pagó con tarjeta por el portal, además de la rectificativa habría que lanzar el `refund` en Stripe. Ahora son dos pasos.
- **Imprimir la rectificativa** con su formato propio.
- **Vale de compra** como alternativa a devolver dinero: muchos salones lo prefieren.
- **Motivos frecuentes** en un desplegable, para no escribirlos cada vez.
