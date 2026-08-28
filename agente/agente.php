<?php

/**
 * =====================================================================
 *  AGENTE CLIMACO POS
 *  Puente entre el panel (en la nube) y el hardware del salón
 * =====================================================================
 *
 *  Un navegador no puede hablar con una impresora ESC/POS ni abrir un
 *  cajón portamonedas. Este agente sondea el servidor, recoge los
 *  trabajos pendientes y los envía al hardware.
 *
 *  Uso:
 *      php agente.php
 *      php agente.php --config=otro.ini
 *      php agente.php --test          (prueba la impresora y sale)
 *
 *  Requiere PHP 8.1 o superior con las extensiones curl y sockets.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Configuración
// ---------------------------------------------------------------------

$rutaConfig = __DIR__ . DIRECTORY_SEPARATOR . 'config.ini';

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--config=')) {
        $rutaConfig = substr($argumento, 9);
    }
}

if (! is_readable($rutaConfig)) {
    salir("No encuentro el fichero de configuración: {$rutaConfig}\n"
        . "Copia config.ini.ejemplo a config.ini y rellénalo.");
}

$config = parse_ini_file($rutaConfig, true);

if ($config === false) {
    salir("El fichero de configuración tiene errores de formato.");
}

$URL    = rtrim($config['servidor']['url'] ?? '', '/');
$TOKEN  = $config['servidor']['token'] ?? '';
$LOG    = $config['agente']['log'] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'agente.log');
$DEBUG  = (bool) ($config['agente']['debug'] ?? false);

if ($URL === '' || $TOKEN === '') {
    salir("Faltan 'url' o 'token' en la sección [servidor] del config.ini.");
}

// ---------------------------------------------------------------------
// Arranque
// ---------------------------------------------------------------------

registrar('==============================================');
registrar('Agente CLIMACO POS iniciado');
registrar('Servidor: ' . $URL);

$saludo = peticion('GET', '/agente/saludo');

if (! $saludo || empty($saludo['ok'])) {
    salir("No he podido conectar con el servidor o el token no es válido.\n"
        . "Comprueba la URL y el token en config.ini.");
}

registrar('Conectado a: ' . ($saludo['empresa'] ?? '?')
    . ' · Terminal: ' . ($saludo['terminal']['nombre'] ?? '?'));

$hardware = $saludo['config'] ?? [];
$intervalo = max(500, (int) ($hardware['intervalo_ms'] ?? 1500));

registrar('Impresora: ' . descripcionImpresora($hardware));
registrar('Sondeo cada ' . $intervalo . ' ms. Ctrl+C para parar.');
registrar('----------------------------------------------');

// Modo prueba: imprime y sale
if (in_array('--test', $argv, true)) {
    registrar('Modo prueba: enviando ticket de comprobación...');

    $prueba = "\x1B@\x1Ba\x01\x1BE\x01PRUEBA DEL AGENTE\x1BE\x00\n"
            . date('d/m/Y H:i:s') . "\n\n"
            . "Si lees esto, la impresora\nesta bien configurada.\n\n\n\n"
            . "\x1DV\x42\x00";

    $resultado = enviarAImpresora($prueba, $hardware, 'TICKETS');

    registrar($resultado === true ? 'Enviado correctamente.' : 'ERROR: ' . $resultado);
    exit($resultado === true ? 0 : 1);
}

// ---------------------------------------------------------------------
// Bucle principal
// ---------------------------------------------------------------------

$fallosSeguidos = 0;

while (true) {
    $respuesta = peticion('GET', '/agente/trabajos');

    if (! $respuesta) {
        $fallosSeguidos++;

        // Sin conexión: se espera más entre intentos, hasta 30 s.
        // Machacar el servidor cada segundo cuando está caído no ayuda.
        $espera = min(30, 2 ** min(5, $fallosSeguidos));

        if ($fallosSeguidos === 1 || $fallosSeguidos % 10 === 0) {
            registrar("Sin conexión con el servidor. Reintento en {$espera} s.");
        }

        sleep($espera);
        continue;
    }

    if ($fallosSeguidos > 0) {
        registrar('Conexión recuperada.');
        $fallosSeguidos = 0;
    }

    // La configuración puede cambiar desde el panel sin reiniciar el agente
    if (! empty($respuesta['config'])) {
        $hardware = $respuesta['config'];
        $intervalo = max(500, (int) ($hardware['intervalo_ms'] ?? 1500));
    }

    foreach ($respuesta['trabajos'] ?? [] as $trabajo) {
        procesarTrabajo($trabajo, $hardware);
    }

    usleep($intervalo * 1000);
}

// =====================================================================
// FUNCIONES
// =====================================================================

function procesarTrabajo(array $trabajo, array $hardware): void
{
    $id = $trabajo['id'];
    $descripcion = $trabajo['descripcion'] ?? $trabajo['tipo'];

    registrar("Trabajo #{$id} · {$trabajo['tipo']} · {$descripcion}");

    $datos = base64_decode($trabajo['payload'] ?? '', true);

    if ($datos === false) {
        confirmar($id, false, 'El contenido del trabajo no es válido.');

        return;
    }

    $resultado = match ($trabajo['destino']) {
        'VISOR' => enviarAVisor($datos, $hardware),
        'CAJON' => enviarAImpresora($datos, $hardware, 'TICKETS'),
        default => enviarAImpresora($datos, $hardware, $trabajo['destino']),
    };

    if ($resultado === true) {
        registrar("  Hecho.");
        confirmar($id, true);
    } else {
        registrar("  ERROR: {$resultado}");
        confirmar($id, false, $resultado);
    }
}

/**
 * Envía bytes a la impresora.
 *
 * Dos modos:
 *   RED   - socket TCP al puerto 9100 (lo habitual en impresoras de red)
 *   LOCAL - se escribe en el puerto compartido de Windows
 */
function enviarAImpresora(string $datos, array $hardware, string $destino): string|bool
{
    $modo = $hardware['impresora_tickets_modo'] ?? 'RED';

    if ($destino === 'ETIQUETAS' && ! empty($hardware['impresora_etiquetas_ip'])) {
        return enviarPorSocket(
            $datos,
            $hardware['impresora_etiquetas_ip'],
            (int) ($hardware['impresora_etiquetas_puerto'] ?? 9100)
        );
    }

    if ($modo === 'LOCAL') {
        return enviarAPuertoLocal($datos, $hardware['impresora_tickets_local'] ?? '');
    }

    $ip = $hardware['impresora_tickets_ip'] ?? '';

    if ($ip === '') {
        return 'No hay impresora configurada en el panel.';
    }

    return enviarPorSocket($datos, $ip, (int) ($hardware['impresora_tickets_puerto'] ?? 9100));
}

function enviarPorSocket(string $datos, string $ip, int $puerto, int $timeout = 5): string|bool
{
    $conexion = @fsockopen($ip, $puerto, $codigo, $mensaje, $timeout);

    if (! $conexion) {
        return "No se pudo conectar con {$ip}:{$puerto} ({$mensaje})";
    }

    stream_set_timeout($conexion, $timeout);

    $escritos = @fwrite($conexion, $datos);
    fflush($conexion);
    fclose($conexion);

    if ($escritos === false || $escritos < strlen($datos)) {
        return 'La impresora aceptó la conexión pero no todos los datos.';
    }

    return true;
}

/**
 * Impresora conectada por USB o LPT en Windows.
 *
 * Requiere compartirla y apuntar aquí el nombre del recurso, por ejemplo
 * \\NOMBREPC\TICKETS. Es menos fiable que la red: si la impresora es de
 * red, usa siempre el modo RED.
 */
function enviarAPuertoLocal(string $datos, string $puerto): string|bool
{
    if ($puerto === '') {
        return 'No hay puerto local configurado.';
    }

    $manejador = @fopen($puerto, 'wb');

    if (! $manejador) {
        return "No se pudo abrir el puerto {$puerto}. Comprueba que la impresora esté compartida.";
    }

    @fwrite($manejador, $datos);
    @fclose($manejador);

    return true;
}

/** Visor de cliente por puerto serie. */
function enviarAVisor(string $datos, array $hardware): string|bool
{
    $puerto = $hardware['visor_puerto'] ?? '';

    if ($puerto === '') {
        return 'No hay visor configurado.';
    }

    $baudios = (int) ($hardware['visor_baudios'] ?? 9600);

    if (esWindows()) {
        // Windows necesita configurar el puerto antes de escribir en él
        @exec("mode {$puerto}: BAUD={$baudios} PARITY=n DATA=8 STOP=1 xon=off 2>&1", $salida, $codigo);

        if ($codigo !== 0) {
            return "No se pudo configurar el puerto {$puerto}.";
        }
    }

    $ruta = esWindows() ? '\\\\.\\' . $puerto : $puerto;
    $manejador = @fopen($ruta, 'wb');

    if (! $manejador) {
        return "No se pudo abrir el puerto {$puerto}.";
    }

    // Limpiar pantalla del visor y escribir
    @fwrite($manejador, "\x0C" . $datos);
    @fclose($manejador);

    return true;
}

function confirmar(int $id, bool $ok, ?string $error = null): void
{
    peticion('POST', "/agente/trabajos/{$id}/confirmar", [
        'ok'    => $ok,
        'error' => $error,
    ]);
}

function peticion(string $metodo, string $ruta, ?array $cuerpo = null): ?array
{
    global $URL, $TOKEN, $DEBUG;

    $curl = curl_init($URL . $ruta);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $TOKEN,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ]);

    if ($cuerpo !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    }

    $respuesta = curl_exec($curl);
    $codigo    = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($curl);

    curl_close($curl);

    if ($respuesta === false) {
        if ($DEBUG) {
            registrar("  [curl] {$errorCurl}");
        }

        return null;
    }

    if ($codigo === 401) {
        salir("El servidor rechaza el token. Genera uno nuevo desde el panel:\n"
            . "Ajustes > Hardware > Generar token del agente.");
    }

    if ($codigo >= 400) {
        if ($DEBUG) {
            registrar("  [http {$codigo}] " . substr((string) $respuesta, 0, 300));
        }

        return null;
    }

    $datos = json_decode((string) $respuesta, true);

    return is_array($datos) ? $datos : null;
}

function descripcionImpresora(array $hardware): string
{
    if (($hardware['impresora_tickets_modo'] ?? 'RED') === 'LOCAL') {
        return 'local en ' . ($hardware['impresora_tickets_local'] ?: 'sin configurar');
    }

    return ($hardware['impresora_tickets_ip'] ?: 'sin configurar')
         . ':' . ($hardware['impresora_tickets_puerto'] ?? 9100);
}

function registrar(string $mensaje): void
{
    global $LOG;

    $linea = date('[Y-m-d H:i:s] ') . $mensaje;

    echo $linea . PHP_EOL;

    @file_put_contents($LOG, $linea . PHP_EOL, FILE_APPEND);

    // Rotación simple: si el log pasa de 5 MB, se conserva uno anterior
    if (@filesize($LOG) > 5 * 1024 * 1024) {
        @rename($LOG, $LOG . '.1');
    }
}

function esWindows(): bool
{
    return strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
}

function salir(string $mensaje): never
{
    registrar('FATAL: ' . $mensaje);
    echo PHP_EOL;
    exit(1);
}
