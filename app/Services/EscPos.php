<?php

namespace App\Services;

/**
 * Constructor de comandos ESC/POS.
 *
 * Genera la secuencia de bytes que entiende una impresora térmica.
 * No imprime nada: solo arma el flujo. Quien lo envía a la impresora
 * es el Agente instalado en el salón.
 *
 * Uso:
 *
 *   $esc = new EscPos(48);
 *   $esc->inicializar()
 *       ->centrar()->negrita(true)->linea('PELUQUERIA JECTAN')
 *       ->negrita(false)->izquierda()
 *       ->separador()
 *       ->cortar();
 *
 *   $bytes = $esc->salida();
 */
class EscPos
{
    protected string $buffer = '';

    // --- Caracteres de control
    public const ESC = "\x1B";
    public const GS  = "\x1D";
    public const LF  = "\x0A";
    public const FS  = "\x1C";

    public function __construct(
        public readonly int $columnas = 48,
    ) {
    }

    public function salida(): string
    {
        return $this->buffer;
    }

    public function base64(): string
    {
        return base64_encode($this->buffer);
    }

    public function crudo(string $bytes): self
    {
        $this->buffer .= $bytes;

        return $this;
    }

    // ------------------------------------------------------------------
    // Inicialización
    // ------------------------------------------------------------------

    public function inicializar(): self
    {
        // ESC @ : reinicia la impresora y limpia estilos anteriores.
        // Sin esto, un trabajo hereda la negrita del anterior si el
        // trabajo previo se cortó a medias.
        return $this->crudo(self::ESC . '@')->juegoCaracteres();
    }

    /**
     * Página de códigos. PC858 (19) incluye € y los acentos del español.
     * Si salen caracteres raros, este es el número que hay que cambiar:
     * cada fabricante numera sus tablas de forma distinta.
     */
    public function juegoCaracteres(int $tabla = 19): self
    {
        return $this->crudo(self::ESC . 't' . chr($tabla));
    }

    // ------------------------------------------------------------------
    // Alineación
    //
    // OJO: la alineación debe emitirse ANTES del texto y de su salto de
    // línea. Emitirla después no afecta a la línea ya impresa; es un
    // error clásico y difícil de ver hasta que se imprime en papel.
    // ------------------------------------------------------------------

    public function alinear(string $donde): self
    {
        $codigo = match (strtoupper($donde)) {
            'CENTRO', 'CENTER'   => 1,
            'DERECHA', 'RIGHT'   => 2,
            default              => 0,
        };

        return $this->crudo(self::ESC . 'a' . chr($codigo));
    }

    public function izquierda(): self { return $this->alinear('IZQUIERDA'); }
    public function centrar(): self   { return $this->alinear('CENTRO'); }
    public function derecha(): self   { return $this->alinear('DERECHA'); }

    // ------------------------------------------------------------------
    // Estilos
    // ------------------------------------------------------------------

    public function negrita(bool $activa = true): self
    {
        return $this->crudo(self::ESC . 'E' . chr($activa ? 1 : 0));
    }

    public function subrayado(bool $activo = true): self
    {
        return $this->crudo(self::ESC . '-' . chr($activo ? 1 : 0));
    }

    public function invertido(bool $activo = true): self
    {
        return $this->crudo(self::GS . 'B' . chr($activo ? 1 : 0));
    }

    /** Tamaño: 1 = normal, 2 = doble, hasta 8. */
    public function tamano(int $ancho = 1, int $alto = 1): self
    {
        $ancho = max(1, min(8, $ancho)) - 1;
        $alto  = max(1, min(8, $alto)) - 1;

        return $this->crudo(self::GS . '!' . chr(($ancho << 4) | $alto));
    }

    public function normal(): self
    {
        return $this->tamano(1, 1)->negrita(false)->subrayado(false)->invertido(false);
    }

    // ------------------------------------------------------------------
    // Texto
    // ------------------------------------------------------------------

    public function texto(string $texto): self
    {
        return $this->crudo($this->codificar($texto));
    }

    public function linea(string $texto = ''): self
    {
        return $this->texto($texto)->crudo(self::LF);
    }

    public function saltos(int $cuantos = 1): self
    {
        return $this->crudo(str_repeat(self::LF, max(0, $cuantos)));
    }

    public function separador(string $caracter = '-'): self
    {
        return $this->linea(str_repeat($caracter, $this->columnas));
    }

    /**
     * Dos columnas: descripción a la izquierda, importe a la derecha,
     * rellenando el hueco con espacios. Es la base de todo ticket.
     */
    public function fila(string $izquierda, string $derecha, int $anchoDerecha = 12): self
    {
        $anchoIzq = $this->columnas - $anchoDerecha;

        $izquierda = $this->recortar($izquierda, $anchoIzq);
        $derecha   = str_pad($this->recortar($derecha, $anchoDerecha), $anchoDerecha, ' ', STR_PAD_LEFT);

        return $this->linea(str_pad($izquierda, $anchoIzq) . $derecha);
    }

    /** Tres columnas: cantidad, descripción, importe. */
    public function filaLinea(string $cantidad, string $descripcion, string $importe): self
    {
        $anchoCant = 4;
        $anchoImp  = 10;
        $anchoDesc = $this->columnas - $anchoCant - $anchoImp - 1;

        return $this->linea(
            str_pad($this->recortar($cantidad, $anchoCant), $anchoCant) . ' ' .
            str_pad($this->recortar($descripcion, $anchoDesc), $anchoDesc) .
            str_pad($this->recortar($importe, $anchoImp), $anchoImp, ' ', STR_PAD_LEFT)
        );
    }

    /** Texto largo partido en varias líneas sin cortar palabras. */
    public function parrafo(string $texto, ?int $ancho = null): self
    {
        $ancho ??= $this->columnas;

        foreach (explode("\n", wordwrap($texto, $ancho, "\n", true)) as $linea) {
            $this->linea($linea);
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Códigos QR
    // ------------------------------------------------------------------

    public function qr(string $datos, int $tamano = 6): self
    {
        $longitud = strlen($datos) + 3;
        $pL = $longitud % 256;
        $pH = intdiv($longitud, 256);

        return $this
            // Modelo 2
            ->crudo(self::GS . '(k' . chr(4) . chr(0) . chr(49) . chr(65) . chr(50) . chr(0))
            // Tamaño del módulo
            ->crudo(self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(67) . chr(max(1, min(16, $tamano))))
            // Corrección de errores: M
            ->crudo(self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(69) . chr(49))
            // Datos
            ->crudo(self::GS . '(k' . chr($pL) . chr($pH) . chr(49) . chr(80) . chr(48) . $datos)
            // Imprimir
            ->crudo(self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(81) . chr(48));
    }

    public function codigoBarras(string $datos, int $tipo = 73): self
    {
        // GS h: altura, GS w: ancho, GS H: posición del texto
        return $this
            ->crudo(self::GS . 'h' . chr(64))
            ->crudo(self::GS . 'w' . chr(2))
            ->crudo(self::GS . 'H' . chr(2))
            ->crudo(self::GS . 'k' . chr($tipo) . chr(strlen($datos)) . $datos);
    }

    // ------------------------------------------------------------------
    // Imágenes
    // ------------------------------------------------------------------

    /**
     * Logotipo en modo raster (GS v 0).
     *
     * Convierte la imagen a blanco y negro puro con umbral: las
     * impresoras térmicas no tienen grises, y una foto sin binarizar
     * sale como una mancha negra.
     */
    public function imagen(string $rutaFichero, int $anchoMax = 384): self
    {
        if (! is_readable($rutaFichero) || ! function_exists('imagecreatefromstring')) {
            return $this;
        }

        $imagen = @imagecreatefromstring(file_get_contents($rutaFichero));

        if (! $imagen) {
            return $this;
        }

        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);

        // El ancho debe ser múltiplo de 8: cada byte son 8 puntos
        $anchoDestino = min($anchoMax, $ancho);
        $anchoDestino = (int) (floor($anchoDestino / 8) * 8);

        if ($anchoDestino < 8) {
            imagedestroy($imagen);

            return $this;
        }

        $altoDestino = (int) round($alto * ($anchoDestino / $ancho));

        $lienzo = imagecreatetruecolor($anchoDestino, $altoDestino);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $imagen, 0, 0, 0, 0, $anchoDestino, $altoDestino, $ancho, $alto);
        imagedestroy($imagen);

        $bytesPorFila = intdiv($anchoDestino, 8);
        $datos = '';

        for ($y = 0; $y < $altoDestino; $y++) {
            for ($bloque = 0; $bloque < $bytesPorFila; $bloque++) {
                $byte = 0;

                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $bloque * 8 + $bit;
                    $color = imagecolorat($lienzo, $x, $y);

                    $luminancia = (($color >> 16) & 0xFF) * 0.299
                                + (($color >> 8) & 0xFF) * 0.587
                                + ($color & 0xFF) * 0.114;

                    // Umbral 128: por debajo se imprime (punto negro)
                    if ($luminancia < 128) {
                        $byte |= (0x80 >> $bit);
                    }
                }

                $datos .= chr($byte);
            }
        }

        imagedestroy($lienzo);

        return $this->crudo(
            self::GS . 'v0' . chr(0)
            . chr($bytesPorFila % 256) . chr(intdiv($bytesPorFila, 256))
            . chr($altoDestino % 256) . chr(intdiv($altoDestino, 256))
            . $datos
        );
    }

    // ------------------------------------------------------------------
    // Hardware
    // ------------------------------------------------------------------

    public function cortar(bool $parcial = true): self
    {
        return $this->saltos(4)->crudo(self::GS . 'V' . chr($parcial ? 66 : 65) . chr(0));
    }

    /**
     * Abre el cajón portamonedas.
     *
     * El pin depende del cableado: casi siempre es el 2, pero algunos
     * cajones usan el 5. Si no abre, es lo primero que hay que probar.
     */
    public function abrirCajon(int $pin = 2, int $encendido = 25, int $apagado = 250): self
    {
        return $this->crudo(
            self::ESC . 'p' . chr($pin === 5 ? 1 : 0)
            . chr(intdiv($encendido, 2)) . chr(intdiv($apagado, 2))
        );
    }

    public function pitido(int $veces = 1): self
    {
        return $this->crudo(self::ESC . 'B' . chr(max(1, min(9, $veces))) . chr(3));
    }

    // ------------------------------------------------------------------
    // Codificación
    // ------------------------------------------------------------------

    /**
     * Pasa de UTF-8 a la página de códigos de la impresora.
     * Sin esto, «Peluquería» sale como «PeluquerÃ­a».
     */
    protected function codificar(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'CP858//TRANSLIT', $texto);

        if ($convertido === false) {
            $convertido = @iconv('UTF-8', 'CP437//TRANSLIT', $texto);
        }

        return $convertido !== false ? $convertido : $this->sinAcentos($texto);
    }

    protected function sinAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', '€' => 'EUR', '·' => '-', '—' => '-', '–' => '-',
            '«' => '"', '»' => '"', '“' => '"', '”' => '"', '’' => "'",
        ]);
    }

    /** Recorta respetando el ancho en caracteres de la impresora. */
    protected function recortar(string $texto, int $ancho): string
    {
        return mb_strlen($texto) > $ancho
            ? mb_substr($texto, 0, $ancho - 1) . '.'
            : $texto;
    }

    // ------------------------------------------------------------------
    // Anchos habituales
    // ------------------------------------------------------------------

    public static function columnasPara(int $anchoMm): int
    {
        return $anchoMm <= 58 ? 32 : 48;
    }
}
