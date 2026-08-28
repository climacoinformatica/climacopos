# CLIMACO POS — Fase 6 · Impresión, hardware y Agente

ESC/POS, cola de impresión, cajón portamonedas, visor de cliente, configuración por terminal y diseñador de ticket. Más el **Agente CLIMACO**, escrito en PHP.

---

## 1. Instalación

### 1.1 Registrar el middleware del agente

En `bootstrap/app.php`, añade `'agente'` a la lista de alias que ya tienes:

```php
$middleware->alias([
    'terminal' => \App\Http\Middleware\VerificarTerminal::class,
    'salon'    => \App\Http\Middleware\AutenticarSalon::class,
    'permiso'  => \App\Http\Middleware\VerificarPermiso::class,
    'agente'   => \App\Http\Middleware\AutenticarAgente::class,   // ← nuevo
]);
```

### 1.2 Lo demás

```powershell
cd C:\xampp\htdocs\climacopos
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
```

Ctrl+Shift+R en el navegador.

---

## 2. Cómo funciona la impresión

```
   Panel (nube)              Base de datos            PC del salón
  ┌────────────┐            ┌──────────────┐        ┌──────────────┐
  │ Se cobra   │───encola──▶│ cola_impresion│◀─sondea│    Agente    │
  │ un ticket  │            │  bytes ESC/POS│        │   (PHP CLI)  │
  └────────────┘            └──────────────┘        └──────┬───────┘
                                                            │ socket
                                                            ▼
                                                     ┌──────────────┐
                                                     │  Impresora   │
                                                     │  Cajón/Visor │
                                                     └──────────────┘
```

**La cola, y no una conexión directa, es la decisión importante.** Si el PC está apagado o sin red cuando se cobra, el trabajo espera en vez de perderse; al arrancar el agente, se imprime. Además, atraviesa cualquier NAT y funciona igual desde una tablet que desde el PC.

Un trabajo recogido y no confirmado en dos minutos vuelve a la cola: si el equipo se apagó justo después de recogerlo, el ticket no llegó a salir. Tras cinco fallos deja de reintentarse y se genera un aviso en el panel.

---

## 3. El Agente

Está en la carpeta `agente/`. Se copia al PC del salón y lleva su propio `LEEME.md` con las instrucciones para el cliente.

En resumen: instalar PHP (o dejar un PHP portable en la misma carpeta), copiar `config.ini.ejemplo` a `config.ini`, pegar la URL y el token que da el panel, y ejecutar `agente.bat`.

**Modo de conexión**: RED por socket TCP al puerto 9100 —lo recomendable— o LOCAL para impresoras USB compartidas en Windows.

**El token se guarda hasheado** en el servidor y solo se muestra una vez. El agente únicamente puede leer sus propios trabajos y confirmarlos: no tiene acceso a ventas, clientes ni ajustes.

---

## 4. Detalles de ESC/POS que cuestan un rollo de papel aprender

Están todos resueltos en `App\Services\EscPos`, con un test cada uno.

**`ESC @` al principio de cada trabajo.** Sin inicializar, un ticket hereda la negrita o el doble alto del anterior si aquel se cortó a medias.

**La alineación se emite ANTES del texto y de su salto de línea.** Emitirla después no afecta a la línea ya impresa. Es el error clásico y no se ve hasta que sale el papel.

**Los acentos hay que convertirlos.** UTF-8 directo imprime «PeluquerÃ­a». Se convierte a CP858, que incluye € y los acentos del español. Si un modelo concreto usa otra tabla, se cambia el número en `juegoCaracteres()`.

**Las imágenes hay que binarizarlas.** Las térmicas no tienen grises: un logo con degradado sale como una mancha negra. Se aplica umbral de luminancia a 128 y el ancho se ajusta a múltiplo de 8, porque cada byte son 8 puntos.

**El pin del cajón.** Casi siempre es el 2, pero algunos usan el 5. Si no abre, es lo primero que hay que probar, y está en el desplegable de configuración por eso.

---

## 5. El diseñador de ticket

*Ajustes → Diseño del ticket*. Líneas de cabecera y pie con alineación, negrita, doble alto y doble ancho; logotipo con su alineación y ancho; y qué se imprime.

Lleva **vista previa en vivo** que se actualiza mientras escribes. Es aproximada —la fuente real depende de la impresora— pero evita gastar papel en cada prueba.

Los datos fiscales (razón social, NIF, dirección) se imprimen solos: no hay que ponerlos en la cabecera.

**El cajón no se abre en cobros con tarjeta**, ni en documentos de formación. Hay un test para cada caso.

---

## 6. Configuración de hardware

*Ajustes → Hardware*. Una tarjeta por terminal con impresora, cajón, visor y sondeo, más los tres botones de prueba: imprimir, abrir cajón y probar visor.

Debajo, la **cola de impresión** con su estado, los errores y un botón para reimprimir cualquier trabajo.

Arriba de cada terminal se indica cuándo se conectó el agente por última vez, y avisa en rojo si lleva más de cinco minutos sin dar señales.

---

## 7. Probar

```powershell
php artisan test --filter=ImpresionTest
```

Veinte pruebas: comandos ESC/POS, anchos exactos de columna, conversión de acentos, pin del cajón, QR, ticket completo, avisos de formación, y el ciclo de la cola con sus reintentos.

Sin impresora física puedes comprobar el flujo entero: **Ajustes → Hardware → Imprimir prueba**, y verás el trabajo aparecer en la cola como pendiente. Con el agente arrancado pasará a «Hecho».

---

## 8. Pendiente

- **Reimpresión desde el listado de tickets**: la lógica está (`GestorImpresion::ticket($ticket, esCopia: true)`), falta el botón.
- **Impresión automática al cobrar**: ahora hay que pedirla. Es un ajuste de una línea, pero prefiero que lo decidas tú: no todos los salones quieren ticket en papel siempre.
- **Ticket por email o WhatsApp** en lugar de papel.
- **Visor en tiempo real** mostrando el importe según se teclea: requiere que el agente sondee más rápido.
- **CloudPRNT** para salones sin PC, solo con tablet.

---

## 9. Siguiente: Fase 7

Informes: ventas por día, familia, artículo, profesional y medio de pago; ocupación de agenda; clientes nuevos frente a recurrentes; comisiones; y el libro de facturas que servirá de base para VERI*FACTU.
