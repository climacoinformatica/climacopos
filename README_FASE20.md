# CLIMACO POS — Fase 20 · Teclado en pantalla

Un teclado propio para importes, PIN y contraseñas, en todo el sistema.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

**Ctrl+Shift+R.** No hay migraciones: solo CSS, JavaScript y dos layouts.

---

## 2. Cómo funciona

**No hay que tocar ninguna vista.** El componente escucha el foco en todo el documento y decide solo qué teclado toca según el tipo de campo:

| Campo | Teclado |
|---|---|
| `type="number"` o `inputmode="decimal"` | Numérico con coma |
| `inputmode="numeric"` o `type="tel"` | Numérico entero |
| `type="password"` | Alfanumérico |
| `data-teclado="pin"` | Dígitos con puntos de progreso |
| `data-teclado="no"` | Ninguno |

Como escucha en el documento y no campo a campo, **funciona también en lo que se crea después**: el modal de cobro, las líneas de fórmula, el buscador de cliente.

Eso significa que ya está activo en sitios que ni hemos tocado: el efectivo contado del arqueo, la recarga de monedero, las cantidades de una devolución, los precios del catálogo.

---

## 3. Decisiones

### 3.1 Teclado propio, no el del sistema

En una tablet, el teclado de Android o iPad ocupa media pantalla, tapa el campo y a veces desplaza el diseño. Y en un PC con pantalla táctil no aparece ninguno. Este siempre está donde se espera y con el mismo aspecto.

### 3.2 Nunca se bloquea el teclado físico

Quien tiene teclado sigue escribiendo con él. El de pantalla es un añadido, no un sustituto: forzarlo molestaría a quien trabaja en el PC del despacho.

### 3.3 Teclas de 56 píxeles

Es la medida a partir de la cual un dedo acierta sin mirar. Con teclas más pequeñas se pulsa la de al lado, y en un cobro eso significa cobrar 12 € en vez de 21.

### 3.4 `mousedown` con `preventDefault`, no `click`

Este detalle es el que hace que funcione. Con `click`, al pulsar una tecla el campo pierde el foco antes de que llegue el evento: el cursor salta, el teclado se cierra y parece que la tecla no responde.

### 3.5 Se disparan los eventos `input` y `change`

Imprescindible: el TPV recalcula el cambio escuchando `input`. Sin dispararlo, el importe se quedaría congelado aunque el campo cambiara.

### 3.6 La coma se convierte a punto en los `type="number"`

Un `input[type=number]` rechaza la coma: el navegador guarda cadena vacía y el usuario ve **desaparecer** lo que acaba de escribir, sin ningún aviso.

### 3.7 Prefijo `tp-` en todas las clases

La pantalla del selector de usuario ya tenía sus propias `.teclado` y `.tecla` desde la Fase 1, con otro aspecto y otro tamaño. Sin prefijo, las dos hojas se pisarían y el PIN del selector cambiaría de forma sin que nadie entendiera por qué.

### 3.8 Configurable por terminal

*Ajustes → Hardware → Pantalla*, con tres opciones:

- **Automático** (por defecto): aparece si la pantalla es táctil.
- **Siempre**: la tablet del mostrador.
- **Nunca**: el PC del despacho, con su teclado de siempre.

Es por terminal y no por empresa porque un mismo salón tiene las dos cosas.

---

## 4. Sobre las contraseñas

El teclado alfanumérico usa distribución **QWERTY española**, con la Ñ donde se espera. Ordenarlo alfabéticamente sería más «lógico» y mucho peor: nadie encuentra las letras donde no están.

Aun así, conviene una recomendación práctica: **las contraseñas de acceso al salón es mejor que sean numéricas**. Escribir una contraseña con mayúsculas y símbolos en un teclado táctil, con clientas esperando, es lento y genera errores. El PIN de cuatro dígitos ya cubre el acceso diario; la contraseña es para acciones sensibles, y ahí una de ocho dígitos numéricos es razonable.

---

## 5. Probar

No hay tests automáticos: es interfaz, y probarla de verdad requiere tocar la pantalla.

En una tablet o con las herramientas del navegador en modo táctil:

1. **TPV → Cobrar → Efectivo**: el campo de entregado abre el teclado numérico, y el cambio se recalcula al pulsar cada tecla.
2. **Selector de usuario**: el PIN muestra los puntos de progreso.
3. **Caja → efectivo contado**: numérico con coma.
4. **Cobrar con vale**: teclado de texto para el código.

En el PC, si no lo ves, es lo esperado: está en «Automático» y tu pantalla no es táctil. Ponlo en «Siempre» desde Hardware para probarlo.

---

## 6. Pendiente

- **Teclas de importes rápidos** dentro del propio teclado (+5, +10, +20), que en el cobro ahorrarían pulsaciones.
- **Vibración al pulsar** en dispositivos que la admiten: confirma el acierto sin mirar.
- **Revisar el resto de pantallas** con calma en la tablet: seguro que hay campos donde el teclado automático no acierta y hace falta un `data-teclado` explícito.
