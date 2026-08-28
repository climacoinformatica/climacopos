# CLIMACO POS — Fase 18b · Ausencias en el registro de jornada

Cierra los dos cabos que quedaban de las fases 17 y 18.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
php artisan test --filter=FichajesAusenciasTest
```

No hay migraciones. Ctrl+Shift+R por si acaso.

---

## 2. Un día de vacaciones ya no es una incidencia

Antes, quien volvía de quince días de vacaciones se encontraba **quince días marcados en rojo** en su registro. El problema no es estético: cuando un aviso salta siempre, deja de avisar, y las incidencias de verdad —un fichaje de salida que falta— se pierden entre el ruido.

Ahora el registro cruza los fichajes con las ausencias aprobadas.

### 2.1 Solo justifica un día SIN fichajes

Si alguien vino a cubrir una urgencia estando de vacaciones y fichó entrada y salida, **esas horas se cuentan**: trabajó, y el registro tiene que reflejarlo.

Y si fichó la entrada pero olvidó la salida, sigue siendo incidencia. La ausencia justifica no haber fichado, no haber fichado a medias.

### 2.2 Una ausencia pendiente no tapa nada

Hasta que se aprueba, esa persona debía haber fichado. Si la solicitud bastara para justificar el hueco, cualquiera podría cubrir un olvido pidiendo un día después.

### 2.3 Los días de ausencia salen en el CSV

Con su tipo escrito al lado. Un mes con huecos sin explicar levanta preguntas; con «Vacaciones» en la fila, no.

---

## 3. Mi registro de jornada

*Fichar → Mi registro*. Cada persona consulta y **descarga** su propio registro de cualquier mes, sin pedírselo a nadie.

La normativa reconoce ese derecho de acceso. Hacerlo depender de que un responsable exporte el fichero no lo cumple: si el responsable está de vacaciones o no quiere, la persona se queda sin su registro. Por eso la ruta no pide permisos.

Lo que sí ve solo quien gestiona personal es el registro **de los demás**, que es otra cosa.

---

## 4. Probar

```powershell
php artisan test --filter=FichajesAusenciasTest
```

Ocho pruebas, todas de comportamiento: que un día de vacaciones no es incidencia, que trabajar durante las vacaciones sí cuenta, que fichar a medias sigue siendo incidencia, y que una solicitud pendiente no justifica nada.

---

## 5. Lo que queda de personal

- **Aviso por email** al aprobar o rechazar una ausencia.
- **Días del año anterior** arrastrados hasta marzo.
- **Festivos del salón** aparte de las ausencias individuales.
- **Comparar lo fichado con el horario previsto**: detectar retrasos y horas de más.
- **Fichar desde el móvil** con el QR del terminal.
