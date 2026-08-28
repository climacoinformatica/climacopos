# CLIMACO POS — Fase 14 · Bonos, monedero y vales

Cierra los medios de pago que quedaron declarados sin lógica desde la Fase 5.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=BonosTest
```

---

## 2. Tres cosas parecidas pero distintas

Las tres resuelven lo mismo desde ángulos diferentes: dinero que la clienta ya entregó y que se consume después.

| | Qué es | A quién pertenece | Ejemplo |
|---|---|---|---|
| **Bono** | Atado a servicios concretos | A un cliente | «5 manicuras por 60 €» |
| **Monedero** | Saldo libre | A un cliente | Recarga de 100 € |
| **Vale** | Importe al portador, con código | A quien lo tenga | Tarjeta regalo, devolución |

La diferencia importa: un bono no se puede regalar a una amiga, un vale sí.

---

## 3. Decisiones

### 3.1 Dos modalidades de bono, porque los salones usan las dos

**Por sesiones**: se descuenta una sesión por uso, sin mirar el precio del día. Si sube la tarifa, la clienta que compró el bono no se ve afectada, que es justo lo que hace atractivo comprarlo.

**Por saldo**: «recarga 100 € y te damos 120». Se descuenta el importe real de lo consumido.

### 3.2 La caducidad se fija al vender, no al usar

Si se calculara sobre la marcha, cambiar la plantilla alteraría la caducidad de bonos **ya vendidos**. Eso es modificar condiciones a posteriori, y no hay forma de defenderlo ante una clienta que enseña su recibo. Hay un test que lo comprueba.

### 3.3 El bono se emite al cobrar, no al añadirlo al ticket

Si se emitiera al añadir la línea y el cobro no llegara a completarse, la clienta se habría llevado un bono sin pagarlo.

### 3.4 El saldo se mueve ANTES de registrar el cobro

En `cobrar()`, si el medio es monedero o vale, primero se descuenta el saldo real y solo después se crea el cobro. Al revés —registrar y luego descontar— dejaría el ticket cobrado con un monedero en negativo cuando algo fallara. Hay un test que provoca el fallo y verifica que el saldo se queda intacto.

### 3.5 Un vale mayor que el ticket conserva el resto

No se devuelve la diferencia en efectivo: eso convertiría un vale en dinero, que no es lo que se vendió. El saldo restante sigue en el vale para la próxima visita.

### 3.6 Los códigos de vale evitan caracteres ambiguos

Fuera `0/O` y `1/I/L`. El código se dicta por teléfono y se teclea a mano, y en un papel impreso en térmica esos caracteres son indistinguibles.

### 3.7 Los mensajes de error dicen qué pasa

No «bono no disponible», sino «el bono caducó el 12/03/2026» o «al bono le quedan 2 sesiones y se necesitan 3». Quien está en el mostrador con una clienta delante necesita saber qué decirle.

### 3.8 Cada movimiento queda registrado

`bono_movimientos` y `monedero_movimientos` permiten reconstruir el saldo sumando los apuntes, lo que hace detectable cualquier descuadre.

---

## 4. Probar

```powershell
php artisan test --filter=BonosTest
```

Veinticinco pruebas. Las que definen el comportamiento: que la caducidad no cambia a posteriori, que un cobro fallido no toca el saldo, que un vale grande conserva el resto, y que el bono solo cubre lo que le corresponde.

---

## 5. Pendiente

- **Pantalla de gestión** de plantillas de bono en el panel. Ahora se crean por base de datos.
- **Botón en el TPV** para usar un bono: al añadir un servicio, si la clienta tiene bono que lo cubra, ofrecerlo. La lógica está en `bonosPara()`.
- **Ficha del cliente** mostrando bonos, saldo y vales de un vistazo. `saldoTotalDisponible()` ya lo calcula.
- **Emitir vale en la devolución**, como alternativa a devolver dinero. Es la petición más habitual de los salones.
- **Venta de bonos online** desde el portal. La columna `vender_online` está preparada.
- **Aviso de bono a punto de caducar**, que además es una buena excusa comercial para que la clienta vuelva.
