<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

/**
 * Generación de PDF.
 *
 * Se genera al vuelo en cada petición, no se guarda. Un cierre pedido
 * hoy y otro pedido dentro de un mes salen iguales, porque los datos de
 * un cierre ya no cambian: los tickets quedaron marcados al cerrarlo.
 *
 * POR QUÉ DOMPDF Y NO OTRO
 *
 * Convierte HTML a PDF, así que las plantillas se escriben en Blade como
 * cualquier otra vista. La alternativa era dibujar cada documento a mano
 * con FPDF: más rápido, pero mucho más trabajo por cada informe nuevo.
 *
 * Para listados de un trimestre va sobrado. Si algún día alguien pide
 * un año entero de facturas y tarda, se puede pasar a FPDF ese caso
 * concreto sin tocar lo demás.
 */
class GeneradorPdf
{
    /** Devuelve los bytes del PDF a partir de una vista Blade. */
    public function desdeVista(string $vista, array $datos = [], string $orientacion = 'portrait'): string
    {
        $opciones = new Options();

        /**
         * Se permite cargar imágenes locales: hace falta para el
         * logotipo del salón, que vive en su storage.
         */
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('chroot', base_path());

        // Sin esto los acentos salen como signos de interrogación
        $opciones->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opciones);

        $dompdf->loadHtml(view($vista, $datos)->render(), 'UTF-8');
        $dompdf->setPaper('A4', $orientacion);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Nombre del fichero.
     *
     * Con el nombre del salón delante: quien archiva los PDF de varios
     * clientes no quiere veinte ficheros llamados «cierre.pdf».
     */
    public function nombre(string $tipo, ?Carbon $fecha = null, ?Carbon $hasta = null): string
    {
        $partes = [
            \Illuminate\Support\Str::slug(tenant('nombre_comercial') ?? 'salon'),
            $tipo,
        ];

        if ($fecha) {
            $partes[] = $fecha->format('Ymd');
        }

        if ($hasta && ! $hasta->isSameDay($fecha ?? $hasta)) {
            $partes[] = $hasta->format('Ymd');
        }

        return implode('_', $partes) . '.pdf';
    }
}
