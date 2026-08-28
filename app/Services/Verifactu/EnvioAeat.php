<?php

namespace App\Services\Verifactu;

use App\Models\Aviso;
use App\Models\VerifactuRegistro;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envío de registros a la AEAT.
 *
 * Se hace por HTTP con certificado de cliente. La conexión exige
 * autenticación mutua TLS: el certificado identifica a quien declara.
 *
 * ─────────────────────────────────────────────────────────────────────
 *  DECISIÓN LEGAL PENDIENTE
 *
 *  ¿Con qué certificado se firma? Hay tres caminos y la elección tiene
 *  consecuencias, así que conviene consultarla con un asesor fiscal:
 *
 *  a) El certificado de cada salón, custodiado por la plataforma.
 *     Funciona, pero implica guardar las credenciales fiscales de
 *     terceros con todo lo que eso conlleva.
 *
 *  b) Tu certificado como colaborador social o representante, con
 *     apoderamiento de cada cliente ante la AEAT. Más limpio a diario,
 *     requiere trámite previo con cada salón.
 *
 *  c) Generar los registros y que el cliente los envíe. Descarta el
 *     automatismo, que es justo el valor del producto.
 *
 *  El código está preparado para (a) y (b): lo único que cambia es de
 *  dónde sale el certificado.
 * ─────────────────────────────────────────────────────────────────────
 */
class EnvioAeat
{
    public function __construct(
        protected GeneradorXml $generador = new GeneradorXml(),
    ) {
    }

    public function enviar(VerifactuRegistro $registro): bool
    {
        if ($registro->estaEnviado()) {
            return true;
        }

        $certificado = $this->certificado();

        if (! $certificado) {
            $registro->update([
                'estado'        => 'ERROR_ENVIO',
                'mensaje_error' => 'No hay certificado configurado para enviar a la AEAT.',
                'intentos'      => $registro->intentos + 1,
            ]);

            return false;
        }

        $xml = $registro->xml ?: $this->generador->registro($registro);

        $registro->update(['estado' => 'ENVIANDO', 'xml' => $xml, 'intentos' => $registro->intentos + 1]);

        try {
            $respuesta = Http::withOptions([
                    'cert'    => [$certificado['ruta'], $certificado['clave']],
                    'timeout' => 30,
                ])
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '',
                ])
                ->send('POST', $this->endpoint(), ['body' => $this->sobreSoap($xml)]);
        } catch (\Throwable $e) {
            return $this->fallo($registro, 'CONEXION', $e->getMessage());
        }

        if ($respuesta->failed()) {
            return $this->fallo($registro, 'HTTP_' . $respuesta->status(),
                mb_substr($respuesta->body(), 0, 500));
        }

        return $this->procesarRespuesta($registro, $respuesta->body());
    }

    /** Envía todo lo pendiente. Lo llama el comando programado. */
    public function enviarPendientes(int $limite = 100): array
    {
        $enviados = 0;
        $fallidos = 0;

        foreach (VerifactuRegistro::pendientes()->orderBy('id')->limit($limite)->get() as $registro) {
            $this->enviar($registro) ? $enviados++ : $fallidos++;

            // La AEAT pide no saturar: una pausa breve entre envíos
            usleep(200_000);
        }

        return ['enviados' => $enviados, 'fallidos' => $fallidos];
    }

    // ------------------------------------------------------------------

    protected function procesarRespuesta(VerifactuRegistro $registro, string $cuerpo): bool
    {
        $estado = $this->extraer($cuerpo, 'EstadoEnvio') ?? '';
        $csv    = $this->extraer($cuerpo, 'CSV');
        $codigo = $this->extraer($cuerpo, 'CodigoErrorRegistro');
        $mensaje= $this->extraer($cuerpo, 'DescripcionErrorRegistro');

        $nuevoEstado = match (strtolower($estado)) {
            'correcto'              => 'ACEPTADO',
            'parcialmentecorrecto'  => 'ACEPTADO_CON_ERRORES',
            'incorrecto'            => 'RECHAZADO',
            default                 => 'ERROR_ENVIO',
        };

        $registro->update([
            'estado'        => $nuevoEstado,
            'csv_aeat'      => $csv,
            'codigo_error'  => $codigo,
            'mensaje_error' => $mensaje ? mb_substr($mensaje, 0, 500) : null,
            'respuesta'     => mb_substr($cuerpo, 0, 60000),
            'enviado_en'    => now(),
        ]);

        if ($registro->ticket) {
            $registro->ticket->forceFill(['verifactu_estado' => $nuevoEstado])->saveQuietly();
        }

        /**
         * Un rechazo no es un aviso más: significa que hay una factura
         * emitida que la AEAT no ha aceptado. Tiene que verse.
         */
        if ($nuevoEstado === 'RECHAZADO') {
            Aviso::create([
                'tipo'            => 'ERROR_VERIFACTU',
                'referencia_id'   => $registro->id,
                'titulo'          => 'Factura rechazada por la AEAT',
                'mensaje'         => $registro->serie_numero . ': ' . ($mensaje ?: 'sin detalle'),
                'requiere_accion' => true,
            ]);

            Log::error('VERI*FACTU rechazado', [
                'empresa' => tenant()?->slug,
                'factura' => $registro->serie_numero,
                'codigo'  => $codigo,
                'mensaje' => $mensaje,
            ]);
        }

        return in_array($nuevoEstado, ['ACEPTADO', 'ACEPTADO_CON_ERRORES'], true);
    }

    protected function fallo(VerifactuRegistro $registro, string $codigo, string $mensaje): bool
    {
        $registro->update([
            'estado'        => 'ERROR_ENVIO',
            'codigo_error'  => $codigo,
            'mensaje_error' => mb_substr($mensaje, 0, 500),
        ]);

        Log::warning('VERI*FACTU: fallo de envío', [
            'registro' => $registro->id,
            'codigo'   => $codigo,
        ]);

        return false;
    }

    protected function sobreSoap(string $xml): string
    {
        $contenido = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $xml);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Header/><soapenv:Body>' . $contenido . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    protected function extraer(string $xml, string $etiqueta): ?string
    {
        if (preg_match('/<[^>]*' . preg_quote($etiqueta, '/') . '[^>]*>(.*?)<\//s', $xml, $coincidencias)) {
            return trim($coincidencias[1]);
        }

        return null;
    }

    protected function endpoint(): string
    {
        return config('verifactu.pruebas', true)
            ? config('verifactu.endpoints.pruebas')
            : config('verifactu.endpoints.produccion');
    }

    /**
     * Certificado con el que se firma.
     *
     * Si la empresa tiene el suyo subido, se usa ese. Si no, el de la
     * plataforma como representante. La contraseña se guarda cifrada.
     */
    protected function certificado(): ?array
    {
        $empresa = tenant();

        if (filled($empresa?->certificado_ruta)) {
            $ruta = \Illuminate\Support\Facades\Storage::disk('local')->path($empresa->certificado_ruta);

            if (is_readable($ruta)) {
                return [
                    'ruta'  => $ruta,
                    'clave' => $empresa->certificado_clave
                        ? \Illuminate\Support\Facades\Crypt::decryptString($empresa->certificado_clave)
                        : '',
                ];
            }
        }

        $rutaPlataforma = config_plataforma('verifactu_certificado_ruta');

        if (filled($rutaPlataforma) && is_readable($rutaPlataforma)) {
            return [
                'ruta'  => $rutaPlataforma,
                'clave' => (string) config_plataforma('verifactu_certificado_clave', ''),
            ];
        }

        return null;
    }
}
