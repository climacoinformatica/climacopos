<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Logotipo del salon.
 *
 * COMO FUNCIONA
 *
 *   Sin logotipo propio  ->  se usa el de CLIMACO POS
 *   Con logotipo propio  ->  se usa el del salon
 *   Al borrarlo          ->  vuelve el de CLIMACO POS
 *
 * El fichero se guarda en el storage DEL SALON, no en uno compartido:
 * cada empresa tiene su carpeta, y asi al dar de baja un salon se borra
 * todo lo suyo de una vez, sin ir buscando ficheros sueltos.
 */
class GestorLogotipo
{
    /**
     * El que se usa cuando el salon no ha subido el suyo.
     *
     * OJO AL NOMBRE DEL FICHERO. nginx sirve /img con `expires 30d`, asi
     * que sustituir el PNG manteniendo el nombre no sirve de nada: los
     * navegadores siguen dando el viejo durante un mes. Al cambiar la
     * marca hay que cambiar tambien el nombre, que es lo unico que
     * garantiza que se descargue el nuevo.
     */
    public const POR_DEFECTO = 'img/logo-climacopos-bn.png';

    /**
     * Alto al que se guarda.
     *
     * En pantalla se ve a unos 40 px. Con 200 va sobrado incluso en
     * pantallas de alta densidad, y evita que un logotipo sacado del
     * movil ocupe cuatro megas y ralentice cada carga del panel.
     */
    public const ALTO = 200;

    public const MAX_BYTES = 4 * 1024 * 1024;

    public function subir(UploadedFile $fichero): string
    {
        $this->comprobar($fichero);

        $imagen = $this->abrir($fichero);

        // Se redimensiona conservando la proporcion
        $alto  = self::ALTO;
        $ancho = (int) round(imagesx($imagen) * $alto / imagesy($imagen));

        $destino = imagecreatetruecolor($ancho, $alto);

        /**
         * Transparencia.
         *
         * Sin esto, un PNG con fondo transparente sale con fondo NEGRO,
         * que sobre el panel oscuro parece que funciona... hasta que
         * alguien lo mira en el portal, que es claro.
         */
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));

        imagecopyresampled($destino, $imagen, 0, 0, 0, 0,
            $ancho, $alto, imagesx($imagen), imagesy($imagen));

        // Siempre PNG, venga como venga: asi la transparencia se conserva
        $ruta = 'logotipo/' . uniqid('logo_') . '.png';

        ob_start();
        imagepng($destino, null, 8);
        $contenido = ob_get_clean();

        imagedestroy($imagen);
        imagedestroy($destino);

        Storage::disk('tenant')->put($ruta, $contenido);

        // Fuera el anterior, para no acumular ficheros huerfanos
        $this->borrarFichero(tenant('logo'));

        tenant()->update(['logo' => $ruta]);

        return $ruta;
    }

    protected function comprobar(UploadedFile $fichero): void
    {
        if ($fichero->getSize() > self::MAX_BYTES) {
            throw new RuntimeException(
                'La imagen pesa más de 4 MB. Si la has sacado del móvil, '
                . 'cualquier editor la reduce en un momento.'
            );
        }

        $tipos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (! in_array($fichero->getMimeType(), $tipos, true)) {
            throw new RuntimeException(
                'El fichero tiene que ser una imagen: JPG, PNG, WEBP o GIF.'
            );
        }

        [$ancho, $alto] = getimagesize($fichero->getRealPath()) ?: [0, 0];

        if ($ancho < 50 || $alto < 20) {
            throw new RuntimeException('La imagen es demasiado pequeña.');
        }
    }

    protected function abrir(UploadedFile $fichero)
    {
        $ruta = $fichero->getRealPath();

        $imagen = match ($fichero->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($ruta),
            'image/png'  => imagecreatefrompng($ruta),
            'image/webp' => imagecreatefromwebp($ruta),
            'image/gif'  => imagecreatefromgif($ruta),
            default      => false,
        };

        if (! $imagen) {
            throw new RuntimeException('No se ha podido leer la imagen.');
        }

        return $imagen;
    }

    /** Quita el logotipo del salon: vuelve el de CLIMACO POS. */
    public function borrar(): void
    {
        $this->borrarFichero(tenant('logo'));

        tenant()->update(['logo' => null]);
    }

    protected function borrarFichero(?string $ruta): void
    {
        if (blank($ruta)) {
            return;
        }

        try {
            Storage::disk('tenant')->delete($ruta);
        } catch (\Throwable) {
            // Si el fichero ya no esta, no hay nada que hacer
        }
    }
}
