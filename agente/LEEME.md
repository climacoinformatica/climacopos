# Agente CLIMACO POS

Puente entre el panel y el hardware del salón: impresora de tickets, cajón portamonedas y visor de cliente.

---

## Por qué hace falta

Un navegador no puede abrir un socket a una impresora térmica ni activar un cajón portamonedas. El panel deja los trabajos en una cola, y este agente los recoge y los envía al hardware.

Trabajar con cola en lugar de conexión directa tiene una ventaja concreta: si el PC está apagado o sin red cuando se cobra un ticket, el trabajo **espera** en vez de perderse. Al arrancar el agente, se imprime.

---

## Instalación

### 1. PHP

El agente necesita PHP 8.1 o superior con `curl` y `sockets`.

Comprueba si ya lo tienes:

```
php -v
```

Si no, hay dos opciones:

- **PHP portable**: descarga el ZIP de php.net (versión Thread Safe), descomprímelo en una carpeta `php` **dentro de esta misma carpeta**, y en `agente.bat` descomenta la línea `set PHP=%~dp0php\php.exe`.
- **PHP del sistema**: instálalo y añádelo al PATH.

En el `php.ini` deben estar activas:

```ini
extension=curl
extension=openssl
```

### 2. Token

En el panel del salón: **Ajustes → Hardware → Generar token del agente**.

El token se muestra **una sola vez**. Si lo pierdes, genera otro (el anterior deja de valer).

### 3. Configuración

Copia `config.ini.ejemplo` como `config.ini` y rellena:

```ini
[servidor]
url   = https://tusalon.climacopos.com
token = el-token-que-te-dio-el-panel
```

### 4. Configurar la impresora en el panel

**Ajustes → Hardware**, en tu terminal:

- **Modo RED** (recomendado): la IP de la impresora y el puerto, casi siempre 9100. La IP se ve imprimiendo la hoja de autotest de la impresora.
- **Modo LOCAL**: para impresoras USB o LPT. Hay que compartirlas en Windows y poner aquí el nombre del recurso, por ejemplo `\\MIPC\TICKETS`.

Si la impresora es de red, usa siempre RED: es más rápido y mucho más fiable.

### 5. Probar

Doble clic en `probar.bat`. Debe salir un ticket con acentos, una tabla y un código QR.

### 6. Arrancar

Doble clic en `agente.bat`. Deja la ventana abierta mientras el salón trabaje.

---

## Instalarlo como servicio de Windows

Para que arranque solo con el ordenador y no dependa de una ventana abierta, lo más cómodo es NSSM:

```
nssm install ClimacoAgente "C:\ruta\php\php.exe" "C:\ruta\agente\agente.php"
nssm set ClimacoAgente AppDirectory "C:\ruta\agente"
nssm set ClimacoAgente Start SERVICE_AUTO_START
nssm start ClimacoAgente
```

Alternativa sin instalar nada: crear un acceso directo a `agente.bat` en la carpeta de Inicio (`shell:startup`).

---

## Si algo no funciona

Mira `agente.log`, que está en esta misma carpeta. Para más detalle, pon `debug = 1` en el `config.ini`.

**«El servidor rechaza el token»**
El token no es válido o el terminal está desactivado. Genera otro desde el panel.

**«No se pudo conectar con la impresora»**
Comprueba que la IP es correcta y que el PC llega a ella:

```
ping 192.168.1.50
telnet 192.168.1.50 9100
```

Si el ping funciona pero el puerto no responde, la impresora puede tener otro puerto configurado.

**Sale texto con caracteres raros en lugar de acentos**
La impresora usa otra página de códigos. Se cambia en el servidor, en `EscPos::juegoCaracteres()`. La 19 es PC858 y funciona en la mayoría; algunos fabricantes usan otra numeración.

**El cajón no abre**
Casi siempre es el pin. En el panel, en la configuración del terminal, cambia el pin del cajón de 2 a 5 y prueba otra vez.

**El agente funciona pero no llega nada**
Comprueba en el panel, en Ajustes → Hardware, la lista de trabajos: si están en «Pendiente» y no se recogen, el agente está apuntando a otro terminal. Si aparecen como «Hecho» pero no salen del papel, el problema está en la impresora.

---

## Seguridad

El token se guarda **hasheado** en el servidor: ni un volcado de la base de datos permite suplantar a un agente. El agente solo puede leer sus propios trabajos y confirmarlos; no tiene acceso a ventas, clientes ni ajustes.
