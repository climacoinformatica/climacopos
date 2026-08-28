# CLIMACO POS — Fase 14c · Bonos en pantalla

Gestión de bonos desde el panel y uso desde el TPV.

---

## 1. Instalación

Descomprime **después** de la Fase 14.

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

Ctrl+Shift+R: hay CSS y JavaScript nuevos.

---

## 2. Qué se puede hacer ya

**Panel → Bonos.** Crear packs, ver los vendidos, consultar el detalle de cada uno con todos sus movimientos, y emitir vales.

**En el TPV.** Al añadir un servicio, si la clienta tiene un bono que lo cubre, el TPV lo ofrece. Si acepta, la línea queda a cero y se descuenta la sesión.

---

## 3. Decisiones

### 3.1 Crear un bono crea también su artículo de venta

Sin esto, el salón tendría que acordarse de crear a mano un artículo con el mismo precio y enlazarlo a la plantilla. Es un paso que se olvida siempre, y deja el bono invendible desde el TPV sin ninguna pista de por qué.

Se crea en una familia llamada «Bonos», que se genera sola la primera vez.

### 3.2 El TPV pregunta, no decide

Cuando hay bono disponible, el TPV lo ofrece y espera confirmación. No lo aplica solo, porque hay casos en que la clienta prefiere pagar y guardar el bono para más adelante, o viene acompañada y el servicio es para otra persona.

Preguntar es también lo que evita el problema contrario: cobrarle dos veces algo que ya tenía pagado.

### 3.3 Desactivar en vez de borrar

Si hay bonos activos de un tipo, no se puede borrar la plantilla: se avisa de que lo desactive. Las clientas que lo compraron tienen que poder seguir usándolo aunque el salón ya no lo venda.

### 3.4 El aviso de caducidad es una herramienta comercial

En «Bonos vendidos» aparece arriba cuántos caducan este mes. No es solo un aviso: llamar a una clienta para decirle que le quedan dos sesiones por usar es una de las excusas más efectivas para que vuelva.

### 3.5 El monedero se ofrece al cobrar

Si la clienta tiene saldo, el modal de cobro lo dice antes de que nadie elija medio de pago. Y si elige monedero sin saldo suficiente, el botón se bloquea con el importe exacto que hay, en vez de dejar intentarlo y fallar.

---

## 4. Cómo probarlo

1. **Bonos → Nuevo bono**: «Bono 5 manicuras», por sesiones, 60 €, 5 sesiones, servicio Manicura, 12 meses.
2. En el TPV verás el bono en la familia «Bonos». Asigna cliente y cóbralo.
3. Abre un ticket nuevo, asigna la misma clienta y toca Manicura: el TPV ofrecerá el bono.
4. Acéptalo. La línea queda a cero y en «Bonos vendidos» verás 4 de 5 sesiones.

---

## 5. Pendiente

- **Buscador de cliente en el TPV**: ahora hay que asignarlo desde la agenda. Es lo que más falta hace para que todo esto luzca.
- **Emitir vale en la devolución**, como alternativa a devolver dinero.
- **Recarga de monedero desde el TPV**, como un artículo más.
- **Imprimir el vale** con su código en formato ticket.
- **Venta de bonos online** desde el portal.
