# CLIMACO POS — Fase 10 · VERI*FACTU

Registro de facturación encadenado, XML, envío a la AEAT y QR en el ticket.

---

## 1. Lo primero: dónde estamos legalmente

El Real Decreto-ley 15/2025, de 2 de diciembre, aplazó de nuevo la obligatoriedad:

| Quién | Desde cuándo |
|---|---|
| Sociedades | 1 de enero de 2027 |
| Autónomos y resto de obligados | 1 de julio de 2027 |

**Pero la prórroga es para quien usa el programa, no para quien lo vende.** El mismo Real Decreto-ley mantiene firmes las obligaciones de productores y comercializadores de software, que debían tener sus productos adaptados desde el 29 de julio de 2025. Comercializar software no adaptado puede suponer multas de hasta 150.000 € por año.

Dicho de otro modo: **CLIMACO POS tiene que cumplir aunque ningún salón lo use todavía**. Y a los salones les vendes tranquilidad con dos años de antelación, que comercialmente no está mal.

Estas fechas han cambiado ya dos veces. Conviene confirmarlas con tu asesor antes de comunicárselas a un cliente.

---

## 2. Instalación

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\actualizar.ps1
php artisan test --filter=VerifactuTest
```

Para activarlo en un salón: *Panel → VERI\*FACTU → Activar*. Hace falta que la empresa tenga NIF.

---

## 3. Cómo funciona

```
  Se cobra un ticket
         │
         ▼
  Se genera el REGISTRO ─── huella SHA-256 que incluye
         │                   la huella del registro anterior
         │
         ├──▶ El ticket guarda su huella y se imprime el QR
         │
         ▼
  Cola de envío ──────────▶ AEAT (SOAP + certificado)
```

**El registro se genera al cobrar; el envío va aparte.** El reglamento exige que el registro exista en el momento de la factura, pero permite el envío diferido. Si la AEAT está caída —que pasa— el salón tiene que poder seguir cobrando.

---

## 4. Decisiones

### 4.1 La tabla de registros es inmutable

El modelo lanza una excepción si alguien intenta modificar un importe o una huella, y otra si intenta borrar un registro. Un error se corrige emitiendo un registro de anulación, igual que en contabilidad no se borra un apunte: se contra-asienta.

### 4.2 La cadena se bloquea al escribir

Dos terminales cobrando a la vez podrían leer el mismo «último registro» y encadenarse los dos a la misma huella. La cadena quedaría bifurcada y la AEAT la rechaza. Por eso se usa `lockForUpdate()` dentro de una transacción.

### 4.3 La formación nunca entra en la cadena

Un ticket de prácticas no genera registro. Declararlos sería comunicar a la Agencia ventas que no han existido. Hay un test que verifica además que **las prácticas no interrumpen la cadena**: si se venden tres tickets reales con prácticas por medio, el tercero sigue encadenando con el segundo.

### 4.4 La marca temporal se guarda, no se recalcula

`FechaHoraHusoGenRegistro` entra en el cálculo de la huella. Si al enviar se recalculara con la hora de ese momento, la huella no coincidiría y el registro sería inválido. Se genera una vez y se conserva.

### 4.5 Verificación de integridad

*Panel → VERI\*FACTU → Verificar ahora* recalcula todas las huellas y comprueba el encadenado. Es lo que haría una inspección. Hay un test que modifica un importe directamente en la base de datos, saltándose el modelo, y comprueba que la verificación lo detecta.

### 4.6 Los importes con dos decimales y punto

`22` y `22.00` tienen que producir la misma huella. Y `-0.00` no existe: un importe que redondee a cero negativo se escribe `0.00`, o la huella no coincide.

---

## 5. Lo que hay que revisar antes de producción

Esto es importante y no lo puedo resolver yo desde aquí.

**El formato exacto de la huella y del XML.** Están implementados según la especificación técnica de la AEAT, pero la Agencia la ha ido revisando. Antes de emitir en real hay que contrastar el orden de los campos con la versión vigente y **validar el XML contra el XSD oficial**. Un elemento fuera de orden se rechaza con un error de validación poco descriptivo.

**Los endpoints.** Los de preproducción y producción están en `config/verifactu.php`. Conviene confirmarlos con la documentación actual.

**El certificado: decisión legal pendiente.** Hay tres caminos y conviene consultarlo con un asesor fiscal:

- **(a)** El certificado de cada salón, custodiado por la plataforma. Funciona —el código lo soporta— pero implica guardar credenciales fiscales de terceros.
- **(b)** Tu certificado como colaborador social o representante, con apoderamiento de cada cliente ante la AEAT. Más limpio en el día a día, requiere trámite previo con cada salón.
- **(c)** Que el cliente envíe. Descarta el automatismo, que es justo el valor del producto.

El código está preparado para (a) y (b): lo único que cambia es de dónde sale el certificado.

**La declaración responsable.** Como fabricante tienes que emitir una declaración responsable de que el software cumple el reglamento. No es código: es un documento. Consúltalo con tu asesor.

---

## 6. Probar

```powershell
php artisan test --filter=VerifactuTest
```

Veintitrés pruebas. Las que más importan:

- Un céntimo distinto cambia la huella.
- Cada registro encadena con el anterior.
- Manipular la base de datos a mano rompe la verificación.
- No se puede modificar ni borrar un registro.
- La formación no genera registro ni interrumpe la cadena.
- El XML está bien formado y lleva huella, NIF y sistema informático.

---

## 7. Pendiente

- **Validación contra el XSD** en los tests, descargando el esquema oficial.
- **Facturas rectificativas (R1–R5)**: ahora solo hay altas y anulaciones. Es la vía correcta para corregir un ticket ya cerrado.
- **Facturas completas (F1)** con datos del destinatario, para cuando una clienta pide factura con su NIF.
- **Libro registro exportable** en el formato que pida la gestoría.
- **Aviso de caducidad del certificado**, que caduca cada uno o dos años y es un fallo silencioso.
- **Modo «no VERI\*FACTU»**: el reglamento admite la alternativa de conservar los registros sin enviarlos, con requisitos de firma más estrictos. Casi nadie la elige, pero existe.
