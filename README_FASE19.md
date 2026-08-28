# CLIMACO POS — Fase 19 · Festivos y horas previstas

Cierra la parte de personal: calendario de cierres y comparación entre lo fichado y lo que tocaba.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
powershell -ExecutionPolicy Bypass -File herramientas\preparar_pruebas.ps1
php artisan test --filter=FestivosTest
```

---

## 2. Festivos

*Panel → Festivos*. Se apoya otra vez en `usuario_excepciones`, que el motor de huecos ya interpreta con `usuario_id = NULL` como «afecta a todo el salón». Tercera fase seguida sin tocar el motor.

Que sea con `usuario_id` nulo y no una excepción por persona importa: así **cubre también a los profesionales que se den de alta después**. Con una excepción por cabeza habría que acordarse de añadirla en cada alta, y no se haría.

### 2.1 La importación es una ayuda, no una fuente oficial

Carga los nueve nacionales fijos, el Día de Canarias y la Semana Santa. Pero **los festivos locales de cada municipio y los traslados que aprueba cada comunidad no los sabemos**, y la pantalla lo dice con claridad. Hay que completarlos mirando el calendario laboral publicado en el boletín.

Preferí avisar antes que dar una lista incompleta con aire de completa.

### 2.2 La Semana Santa se calcula sola

Con el algoritmo de Gauss, cuatro líneas. PHP tiene `easter_date()`, pero necesita la extensión `calendar`, que no siempre está compilada. No merece la pena depender de eso para esto.

### 2.3 Cuántos festivos caen en día que abres

Un festivo en lunes, si el salón cierra los lunes, no quita facturación. La pantalla lo distingue, que es lo que interesa saber al planificar el año.

### 2.4 Media jornada: honestidad sobre lo que no hace

Se puede anotar «abre solo por la mañana», pero **no bloquea la agenda todavía**: hace falta el horario especial, que aún no está. La pantalla lo advierte en vez de dejar creer que funciona.

---

## 3. Lo fichado frente a lo previsto

El informe de jornada ahora compara con el horario configurado en la Fase 3. Sin esa referencia, «8 h 15 min» no dice nada: la información está en la diferencia.

Cada día muestra previsto, real y diferencia, con color solo cuando pasa de un cuarto de hora, para que el ojo vaya a lo que importa.

**Un festivo o una ausencia no tienen jornada prevista.** Exigir ocho horas un 25 de diciembre daría una desviación falsa de ocho horas en rojo, y ese es justo el ruido que hace que nadie mire el informe.

**Trabajar un día que no tocaba se cuenta aparte**, en «fuera de horario». Un domingo trabajado son horas extra enteras, no una desviación del cero.

Si alguien acumula más de cinco horas por encima de su horario, sale un aviso. Las horas de más se compensan o se pagan, pero no desaparecen, y verlas a tiempo evita la conversación incómoda de fin de mes.

---

## 4. Probar

```powershell
php artisan test --filter=FestivosTest
```

Trece pruebas. Las que definen el comportamiento: que un festivo bloquea a todo el salón, que la media jornada no cierra el día entero, que importar dos veces no duplica, y que un festivo no genera desviación en el registro de jornada.

---

## 5. Lo que queda de personal

- **Horario especial** para las medias jornadas y para los días de horario reducido.
- **Aviso por email** al aprobar o rechazar una ausencia.
- **Días del año anterior** arrastrados hasta marzo.
- **Fichar desde el móvil** con el QR del terminal.
- **Calendario laboral impreso** para colgar en el salón.
