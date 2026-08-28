<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\ArticuloFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Subida y redimensionado de imágenes con GD (ya viene en XAMPP,
 * sin dependencias extra tipo Intervention).
 *
 * Todo va al disco 'public', que el FilesystemTenancyBootstrapper
 * separa por empresa: storage/empresa{id}/app/public/...
 */
class GestorImagenes
{
    public const ANCHO_MAX = 1200;
    public const ANCHO_MINI = 320;
    public const CALIDAD = 82;

    /** Sube una foto de artículo, genera miniatura y crea el registro. */
    public function subirFotoArticulo(Articulo $articulo, UploadedFile $fichero, ?string $alt = null): ArticuloFoto
    {
        $carpeta = 'articulos/' . $articulo->id;
        $base    = Str::uuid();

        $ruta     = $this->procesar($fichero, "{$carpeta}/{$base}.jpg", self::ANCHO_MAX);
        $rutaMini = $this->procesar($fichero, "{$carpeta}/{$base}_mini.jpg", self::ANCHO_MINI);

        $esPrimera = $articulo->fotos()->count() === 0;

        return $articulo->fotos()->create([
            'ruta'      => $ruta,
            'ruta_mini' => $rutaMini,
            'alt'       => $alt ?: $articulo->nombre,
            'orden'     => ($articulo->fotos()->max('orden') ?? 0) + 1,
            'principal' => $esPrimera,
        ]);
    }

    public function subirImagenSimple(UploadedFile $fichero, string $carpeta): string
    {
        return $this->procesar($fichero, $carpeta . '/' . Str::uuid() . '.jpg', self::ANCHO_MAX);
    }

    /**
     * Redimensiona manteniendo proporción, corrige la orientación EXIF
     * (las fotos de móvil vienen giradas) y guarda como JPEG.
     */
    protected function procesar(UploadedFile $fichero, string $destino, int $anchoMax): string
    {
        $imagen = $this->abrir($fichero);

        if (! $imagen) {
            // Formato no soportado por GD: se guarda tal cual
            Storage::disk('public')->putFileAs(
                dirname($destino),
                $fichero,
                basename($destino)
            );

            return $destino;
        }

        $imagen = $this->corregirOrientacion($imagen, $fichero);

        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);

        if ($ancho > $anchoMax) {
            $nuevoAlto = (int) round($alto * ($anchoMax / $ancho));
            $destinoGd = imagecreatetruecolor($anchoMax, $nuevoAlto);

            // Fondo blanco: los PNG con transparencia quedarían negros
            imagefill($destinoGd, 0, 0, imagecolorallocate($destinoGd, 255, 255, 255));
            imagecopyresampled($destinoGd, $imagen, 0, 0, 0, 0, $anchoMax, $nuevoAlto, $ancho, $alto);
            imagedestroy($imagen);
            $imagen = $destinoGd;
        }

        ob_start();
        imagejpeg($imagen, null, self::CALIDAD);
        $binario = ob_get_clean();
        imagedestroy($imagen);

        Storage::disk('public')->put($destino, $binario);

        return $destino;
    }

    protected function abrir(UploadedFile $fichero)
    {
        $ruta = $fichero->getRealPath();

        return match ($fichero->getMimeType()) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($ruta),
            'image/png'               => @imagecreatefrompng($ruta),
            'image/gif'               => @imagecreatefromgif($ruta),
            'image/webp'              => function_exists('imagecreatefromwebp')
                                         ? @imagecreatefromwebp($ruta) : null,
            default                   => null,
        };
    }

    /** Las fotos hechas con móvil llegan giradas si no se lee el EXIF. */
    protected function corregirOrientacion($imagen, UploadedFile $fichero)
    {
        if (! function_exists('exif_read_data') || $fichero->getMimeType() !== 'image/jpeg') {
            return $imagen;
        }

        $exif = @exif_read_data($fichero->getRealPath());

        return match ($exif['Orientation'] ?? 1) {
            3       => imagerotate($imagen, 180, 0),
            6       => imagerotate($imagen, -90, 0),
            8       => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
    }

    public function borrarCarpetaArticulo(Articulo $articulo): void
    {
        Storage::disk('public')->deleteDirectory('articulos/' . $articulo->id);
    }
}
