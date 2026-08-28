# CLIMACO POS — Fase 18 · Vacaciones y ausencias

Solicitud, aprobación y cómputo de días, conectado con la agenda.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=AusenciasTest
```

---

## 2. Se apoya en lo que ya había

El motor de huecos respeta `usuario_excepciones` desde la Fase 3, así que **no se ha tocado**. Al aprobar una ausencia se crea su excepción, y la agenda deja de ofrecer huecos sola. Al cancelarla, se retira y esos días vuelven a estar disponibles.

Esto es lo que faltaba encima: el flujo de solicitar, aprobar y rechazar, y el cómputo de días.

---

## 3. Decisiones

### 3.1 No se cuentan los días que el salón cierra

Si Marta trabaja de martes a sábado y pide de lunes a domingo, son **cinco** días de vacaciones, no siete. Contar los siete sería robarle dos días, y es el fallo más común de los sistemas que cuentan días naturales.

El cálculo usa el horario configurado en la Fase 3. Sin horario, se asume lunes a sábado.

### 3.2 Una baja no consume vacaciones

Descontar una baja médica del cupo sería un error con consecuencias laborales. Solo consumen cupo las vacaciones y los asuntos propios; el resto queda en cero y se ve en la propia pantalla al elegir el tipo.

### 3.3 Se puede pedir aunque no queden días

Se avisa, pero no se bloquea. Puede haber acuerdos particulares, días del año anterior o permisos sin sueldo. **Quien aprueba decide con la información delante**; el software no debería impedirlo.

### 3.4 Mientras está pendiente, la persona trabaja

Una solicitud sin aprobar no bloquea la agenda ni cuenta como ausencia. Bloquear al solicitar dejaría huecos sin vender si después se rechaza.

### 3.5 Rechazar exige explicación

Un «no» sin motivo genera más conversaciones de las que ahorra, y además queda por escrito para ambas partes.

### 3.6 El calendario avisa de los solapes

Marca los días en que hay dos o más personas fuera a la vez. Es lo que hay que mirar antes de aprobar: dejar el salón sin nadie un sábado es el error caro, y se ve venir con un mes de antelación.

---

## 4. Quién puede qué

**Cualquiera** puede pedir días y cancelar los suyos: es un derecho, no una función administrativa.

**Con `usuarios.gestionar`** se ven las solicitudes pendientes, se aprueban o rechazan, se registra la ausencia de otra persona —una baja no la mete quien está en cama— y se accede al calendario del equipo. Cuando lo registra un responsable, queda aprobado directamente.

---

## 5. Probar

```powershell
php artisan test --filter=AusenciasTest
```

Diecisiete pruebas. Las que definen el comportamiento: que no se cuentan los días de cierre, que una baja no gasta cupo, que aprobar bloquea la agenda y cancelar la libera, y que una solicitud pendiente no cuenta como ausencia.

En pantalla: pide unos días desde **Ausencias**, apruébalos, y comprueba en la agenda que esos días ya no ofrecen huecos.

---

## 6. Pendiente

- **Que el registro de jornada no marque incidencia** en días de ausencia aprobada. `estaAusente()` ya lo resuelve; falta llamarlo desde `GestorFichajes`.
- **Aviso por email** al aprobar o rechazar.
- **Días del año anterior** que se arrastran hasta marzo, como permite bastante convenio.
- **Festivos del salón** aparte de las ausencias individuales.
- **Acceso del trabajador a su propio registro horario** descargable, que la normativa exige y sigue pendiente desde la fase anterior.
