<?php

namespace App\Services\Verifactu;

use App\Models\Auditoria;
use App\Models\Ticket;
use App\Models\VerifactuRegistro;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Genera los registros de facturación VERI*FACTU.
 *
 * Se llama al cobrar un ticket y al anularlo. El envío a la AEAT va
 * aparte, en cola: si la Agencia está caída, el salón tiene que poder
 * seguir cobrando. El reglamento contempla el envío diferido siempre
 * que el registro se haya generado en el momento de la factura.
 */
class GestorVerifactu
{
    /**
     * Registro de ALTA de una factura.
     *
     * IMPORTANTE: los tickets de formación NO generan registro. No son
     * facturas: no tienen valor fiscal, van en serie propia y quedan
     * fuera de todo. Meterlos en la cadena sería declarar ventas que no
     * han existido.
     */
    public function alta(Ticket $ticket): ?VerifactuRegistro
    {
        if ($ticket->es_formacion) {
            return null;
        }

        if (! $this->activo()) {
            return null;
        }

        // No duplicar si ya existe
        if ($existente = VerifactuRegistro::where('ticket_id', $ticket->id)->where('tipo', 'ALTA')->first()) {
            return $existente;
        }

        return DB::transaction(function () use ($ticket) {
            /**
             * Bloqueo de la cadena.
             *
             * Dos cobros simultáneos en terminales distintos podrían leer
             * el mismo «último registro» y encadenar los dos a la misma
             * huella. La cadena quedaría bifurcada y la AEAT lo rechaza.
             */
            $anterior = VerifactuRegistro::lockForUpdate()->orderByDesc('id')->first();

            $empresa = tenant();
            $marca = HuellaVerifactu::marcaTemporal();

            $datos = [
                'tipo'             => 'ALTA',
                'ticket_id'        => $ticket->id,
                'nif_emisor'       => $this->nifEmisor($empresa),
                'serie_numero'     => $ticket->referencia(),
                'fecha_expedicion' => $ticket->fecha->format('d-m-Y'),
                /**
                 * Tipo de factura.
                 *
                 *   F2  factura simplificada (el ticket normal)
                 *   R5  rectificativa de factura simplificada
                 *
                 * Una devolucion no es una factura negativa suelta: es un
                 * documento que rectifica a otro, y la AEAT quiere saber a
                 * cual y por que.
                 */
                'tipo_factura'     => $ticket->esRectificativa()
                                      ? ($ticket->tipo_rectificativa ?: 'R5')
                                      : 'F2',
                'base'             => (float) $ticket->base,
                'cuota'            => (float) $ticket->impuesto,
                'total'            => (float) $ticket->total,
                'tipo_impositivo'  => $this->tipoImpositivo($ticket),
                'descripcion'      => $this->descripcion($ticket),
                'huella_anterior'  => $anterior?->huella,
                'registro_anterior_id' => $anterior?->id,
                'fecha_hora_huso'  => $marca,
            ];

            $datos['huella'] = HuellaVerifactu::alta($datos);
            $datos['fecha_expedicion'] = $ticket->fecha->toDateString();

            $registro = VerifactuRegistro::create($datos);

            // El ticket guarda su huella para imprimirla en el QR
            $ticket->forceFill([
                'verifactu_hash'          => $registro->huella,
                'verifactu_hash_anterior' => $registro->huella_anterior,
                'verifactu_estado'        => 'PENDIENTE',
            ])->saveQuietly();

            Auditoria::registrar('verifactu_alta', 'verifactu_registros', $registro->id, [
                'factura' => $registro->serie_numero,
                'huella'  => substr($registro->huella, 0, 16),
            ]);

            return $registro;
        });
    }

    /** Registro de ANULACION. */
    public function anulacion(Ticket $ticket): ?VerifactuRegistro
    {
        if ($ticket->es_formacion || ! $this->activo()) {
            return null;
        }

        $alta = VerifactuRegistro::where('ticket_id', $ticket->id)->where('tipo', 'ALTA')->first();

        if (! $alta) {
            // Nunca se declaró: no hay nada que anular
            return null;
        }

        if (VerifactuRegistro::where('ticket_id', $ticket->id)->where('tipo', 'ANULACION')->exists()) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $alta) {
            $anterior = VerifactuRegistro::lockForUpdate()->orderByDesc('id')->first();
            $marca = HuellaVerifactu::marcaTemporal();

            $datos = [
                'tipo'             => 'ANULACION',
                'ticket_id'        => $ticket->id,
                'nif_emisor'       => $alta->nif_emisor,
                'serie_numero'     => $alta->serie_numero,
                'fecha_expedicion' => $alta->fecha_expedicion->format('d-m-Y'),
                'tipo_factura'     => $alta->tipo_factura,
                'total'            => (float) $alta->total,
                'huella_anterior'  => $anterior?->huella,
                'registro_anterior_id' => $anterior?->id,
                'fecha_hora_huso'  => $marca,
            ];

            $datos['huella'] = HuellaVerifactu::anulacion($datos);
            $datos['fecha_expedicion'] = $alta->fecha_expedicion->toDateString();

            $registro = VerifactuRegistro::create($datos);

            Auditoria::registrar('verifactu_anulacion', 'verifactu_registros', $registro->id, [
                'factura' => $registro->serie_numero,
            ]);

            return $registro;
        });
    }

    // ------------------------------------------------------------------

    public function activo(): bool
    {
        $empresa = tenant();

        return (bool) ($empresa?->verifactu_activo ?? false);
    }

    /** Estado de la cadena, para la pantalla de ajustes. */
    public function estado(): array
    {
        $total = VerifactuRegistro::count();

        return [
            'activo'     => $this->activo(),
            'total'      => $total,
            'pendientes' => VerifactuRegistro::pendientes()->count(),
            'aceptados'  => VerifactuRegistro::aceptados()->count(),
            'rechazados' => VerifactuRegistro::where('estado', 'RECHAZADO')->count(),
            'ultimo'     => VerifactuRegistro::ultimo(),
        ];
    }

    protected function nifEmisor($empresa): string
    {
        $nif = preg_replace('/[^A-Z0-9]/i', '', (string) $empresa->nif);

        if (blank($nif)) {
            throw new RuntimeException(
                'La empresa no tiene NIF configurado. Sin NIF no se puede emitir '
                . 'ningún registro de facturación.'
            );
        }

        return strtoupper($nif);
    }

    /** Tipo impositivo predominante del ticket. */
    protected function tipoImpositivo(Ticket $ticket): float
    {
        $linea = $ticket->lineas()->orderByDesc('importe')->first();

        return (float) ($linea?->impuesto_pct ?? 0);
    }

    protected function descripcion(Ticket $ticket): string
    {
        if ($ticket->esRectificativa()) {
            $original = $ticket->rectificaA;

            return mb_substr(
                'Rectificación de ' . ($original?->referencia() ?? 'factura anterior')
                . ($ticket->motivo_rectificacion ? ' · ' . $ticket->motivo_rectificacion : ''),
                0, 500,
            );
        }

        $servicios = $ticket->lineas()->pluck('descripcion')->take(3)->implode(', ');

        return mb_substr($servicios ?: 'Servicios de peluquería y estética', 0, 500);
    }
}
