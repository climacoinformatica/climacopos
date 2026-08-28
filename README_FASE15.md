# CLIMACO POS — Fase 15 · Buscador de cliente en el TPV

Lo que faltaba para que bonos, monedero y vales sirvan de algo en el mostrador.

---

## 1. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
php artisan test --filter=ClienteTpvTest
```

Ctrl+Shift+R: hay CSS y JavaScript nuevos. No hay migraciones.

---

## 2. Qué cambia

En el ticket, debajo del número, hay un botón con el nombre del cliente o «Sin cliente». Al tocarlo se abre el buscador.

Debajo aparece un panel morado con lo que esa clienta tiene ya pagado: saldo del monedero, bonos disponibles y avisos de su ficha.

---

## 3. Decisiones

### 3.1 Se busca por todo a la vez

Nombre, apellidos, teléfono y email en la misma consulta. Quien está en el mostrador escribe lo primero que recuerda —normalmente los cuatro últimos dígitos del móvil— y no debería tener que elegir antes por qué campo busca.

También busca por **nombre y apellido juntos**: «maría lópez» no encuentra nada si se buscan por separado, y escribir el nombre completo es lo natural.

### 3.2 Los resultados enseñan lo que importa

Cada línea muestra la última visita y unas pastillas con el saldo y los bonos. Así se ve **antes de elegir** que esa clienta tiene 40 € en el monedero, en lugar de descubrirlo al cobrar.

### 3.3 Alta rápida con el nombre y ya

Pedir dirección, fecha de nacimiento y consentimientos con una clienta esperando hace que se acabe pulsando «sin cliente». Y entonces se pierden el historial, los bonos y cualquier posibilidad de fidelizar.

El formulario se **precarga con lo que se ha escrito**: si son solo dígitos, va al teléfono; si no, al nombre.

### 3.4 Un teléfono repetido reutiliza la ficha

Si al crear se detecta que ya hay alguien con ese teléfono, se usa esa ficha y se avisa. Los duplicados ensucian el fichero para siempre y parten el historial de una clienta en dos.

### 3.5 Al asignar cliente se revisan las líneas ya tecleadas

Este es el detalle que hace que funcione de verdad. En el mostrador se teclean primero los servicios y se asigna la ficha al cobrar. Si solo se miraran los bonos al añadir cada línea, en ese orden —que es el habitual— no se ofrecería ninguno.

Ahora, al asignar la clienta, se comprueban todas las líneas y se ofrecen los bonos que las cubran.

### 3.6 Los clientes bloqueados no aparecen

Si alguien está marcado como bloqueado, no debe poder asignarse a un ticket.

### 3.7 Búsqueda con retardo de 300 ms

Sin esperar, cada tecla dispara una consulta: escribir «mercedes» son ocho peticiones. Trescientos milisegundos es donde deja de notarse el retardo y dejan de sobrar peticiones.

---

## 4. Probar

```powershell
php artisan test --filter=ClienteTpvTest
```

Dieciséis pruebas. Llaman a los métodos del controlador directamente, sin pasar por HTTP: las rutas del panel exigen terminal vinculado y sesión de salón, y montar eso en cada test añadiría ruido sin comprobar nada más.

En pantalla, el recorrido completo: crea un bono, véndeselo a una clienta, abre un ticket nuevo, **teclea primero el servicio** y luego asigna la ficha. El TPV debe ofrecerte el bono.

---

## 5. Pendiente

- **Ficha completa del cliente** desde el TPV: historial, fórmulas de color, alergias. Es lo siguiente que pedirá cualquier peluquería.
- **Recarga de monedero desde el TPV**, como un artículo más.
- **Emitir vale en la devolución.**
- **Avisos en la ficha** (`avisos_ficha`): el panel ya los pinta, falta la columna y la pantalla para escribirlos.
