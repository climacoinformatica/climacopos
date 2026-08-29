<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CierreJornada;
use App\Models\Ticket;
use App\Services\GeneradorPdf;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Descarga y envio de documentos en PDF.
 *
 * Se generan al vuelo, no se guardan. Los datos de un cierre ya no
 * cambian —los tickets quedaron marcados al cerrarlo—, asi que el PDF
 * de hoy y el de dentro de un mes salen iguales.
 */
class DocumentosController extends Controller
{
    public function __construct(
        protected GeneradorPdf $pdf = new GeneradorPdf(),
    ) {
    }

    // ------------------------------------------------------------------
    //  Cierre de jornada
    // ------------------------------------------------------------------

    public function cierrePdf(CierreJornada $cierre)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::CAJA_CIERRE),
            403,
        );

        $contenido = $this->pdf->desdeVista('pdf.cierre', [
            'cierre' => $cierre->load('usuario'),
        ]);

        return response($contenido, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                . $this->pdf->nombre('cierre', $cierre->fecha_fin) . '"',
        ]);
    }

    public function cierreEnviar(Request $peticion, CierreJornada $cierre)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::CAJA_CIERRE),
            403,
        );

        $datos = $peticion->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Escribe una direccion de correo.',
            'email.email'    => 'Esa direccion no parece valida.',
        ]);

        $contenido = $this->pdf->desdeVista('pdf.cierre', [
            'cierre' => $cierre->load('usuario'),
        ]);

        $enviado = $this->enviarPdf(
            $datos['email'],
            'Cierre de jornada del ' . $cierre->fecha_fin->format('d/m/Y'),
            'correo.plataforma.documento',
            [
                'titulo'      => 'Cierre de jornada',
                'descripcion' => 'del ' . $cierre->fecha_fin->format('d/m/Y'),
            ],
            $contenido,
            $this->pdf->nombre('cierre', $cierre->fecha_fin),
        );

        return $enviado
            ? back()->with('exito', 'Enviado a ' . $datos['email'])
            : back()->with('error',
                'No se ha podido enviar. Comprueba la configuracion de correo.');
    }

    // ------------------------------------------------------------------
    //  Listado de facturas
    // ------------------------------------------------------------------

    public function facturas(Request $peticion)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::INFORMES_VER),
            403,
        );

        [$desde, $hasta] = $this->rango($peticion);

        return view('panel.documentos.facturas', [
            'datos'   => $this->datosFacturas($desde, $hasta),
            'desde'   => $desde,
            'hasta'   => $hasta,
            'atajo'   => $peticion->input('atajo'),
        ]);
    }

    public function facturasPdf(Request $peticion)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::INFORMES_VER),
            403,
        );

        [$desde, $hasta] = $this->rango($peticion);

        $contenido = $this->pdf->desdeVista(
            'pdf.facturas',
            $this->datosFacturas($desde, $hasta),
        );

        return response($contenido, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                . $this->pdf->nombre('facturas', $desde, $hasta) . '"',
        ]);
    }

    public function facturasEnviar(Request $peticion)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::INFORMES_VER),
            403,
        );

        $peticion->validate(['email' => ['required', 'email']]);

        [$desde, $hasta] = $this->rango($peticion);

        $contenido = $this->pdf->desdeVista(
            'pdf.facturas',
            $this->datosFacturas($desde, $hasta),
        );

        $enviado = $this->enviarPdf(
            $peticion->input('email'),
            'Listado de facturas · ' . $desde->format('d/m/Y') . ' a ' . $hasta->format('d/m/Y'),
            'correo.plataforma.documento',
            [
                'titulo'      => 'Listado de facturas',
                'descripcion' => 'del ' . $desde->format('d/m/Y')
                                 . ' al ' . $hasta->format('d/m/Y'),
            ],
            $contenido,
            $this->pdf->nombre('facturas', $desde, $hasta),
        );

        return $enviado
            ? back()->with('exito', 'Enviado a ' . $peticion->input('email'))
            : back()->with('error',
                'No se ha podido enviar. Comprueba la configuracion de correo.');
    }

    // ------------------------------------------------------------------

    /**
     * Los datos del listado, comunes a la pantalla y al PDF.
     *
     * Los documentos de formacion quedan fuera solos, por el global
     * scope: no tienen valor fiscal y no pueden aparecer en nada que vea
     * la gestoria.
     */
    protected function datosFacturas(Carbon $desde, Carbon $hasta): array
    {
        $tickets = Ticket::where('estado', 'COBRADO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with(['cliente', 'lineas'])
            ->orderBy('fecha')
            ->get();

        /**
         * Desglose por tipo de impuesto.
         *
         * Es lo que pide la gestoria para el modelo trimestral: no le
         * vale el total, necesita cuanto hay a cada tipo.
         */
        $porImpuesto = [];

        foreach ($tickets as $ticket) {
            foreach ($ticket->lineas as $linea) {
                $tipo = (float) ($linea->impuesto_pct ?? 0);

                if (! isset($porImpuesto[(string) $tipo])) {
                    $porImpuesto[(string) $tipo] = ['base' => 0.0, 'cuota' => 0.0];
                }

                $base = (float) $linea->importe / (1 + $tipo / 100);

                $porImpuesto[(string) $tipo]['base'] += $base;
                $porImpuesto[(string) $tipo]['cuota'] += (float) $linea->importe - $base;
            }
        }

        foreach ($porImpuesto as $tipo => $importes) {
            $porImpuesto[$tipo] = [
                'base'  => round($importes['base'], 2),
                'cuota' => round($importes['cuota'], 2),
            ];
        }

        ksort($porImpuesto);

        return [
            'tickets'     => $tickets,
            'porImpuesto' => $porImpuesto,
            'desde'       => $desde,
            'hasta'       => $hasta,
        ];
    }

    /**
     * Rango de fechas, con atajos.
     *
     * El trimestre es el que mas se usa: es lo que pide la gestoria cada
     * tres meses.
     */
    protected function rango(Request $peticion): array
    {
        return match ($peticion->input('atajo')) {
            'mes'        => [now()->startOfMonth(), now()->endOfMonth()],
            'mes_p'      => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'trimestre'  => [now()->startOfQuarter(), now()->endOfQuarter()],
            'trimestre_p'=> [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'ano'        => [now()->startOfYear(), now()->endOfYear()],
            default      => [
                $peticion->filled('desde')
                    ? Carbon::parse($peticion->input('desde'))->startOfDay()
                    : now()->startOfMonth(),
                $peticion->filled('hasta')
                    ? Carbon::parse($peticion->input('hasta'))->endOfDay()
                    : now()->endOfDay(),
            ],
        };
    }

    /**
     * Envia un PDF adjunto.
     *
     * Sale por el SMTP de la PLATAFORMA, no por el del salon: es un
     * documento interno que va al gestor o al propietario, no un aviso a
     * una clienta.
     */
    protected function enviarPdf(
        string $destino,
        string $asunto,
        string $plantilla,
        array $datos,
        string $contenido,
        string $nombre,
    ): bool {
        $configurador = new \App\Services\Correo\ConfiguradorCorreo();

        $empresaActual = tenancy()->initialized ? tenant() : null;

        if ($empresaActual) {
            tenancy()->end();
        }

        try {
            if (! $configurador->disponible()) {
                Log::warning('Sin SMTP: no se envio el documento', ['a' => $destino]);

                return false;
            }

            $remitente = $configurador->preparar();

            $datos['salon'] = $empresaActual?->nombre_comercial ?? 'CLIMACO POS';

            Mail::send($plantilla, $datos,
                function ($mensaje) use ($destino, $asunto, $remitente, $contenido, $nombre) {
                    $mensaje->to($destino)
                            ->subject($asunto)
                            ->from($remitente['email'], 'CLIMACO POS')
                            ->attachData($contenido, $nombre, ['mime' => 'application/pdf']);
                });

            Log::info('Documento enviado', ['a' => $destino, 'asunto' => $asunto]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar el documento', [
                'a'     => $destino,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            if ($empresaActual) {
                tenancy()->initialize($empresaActual);
            }
        }
    }
}
