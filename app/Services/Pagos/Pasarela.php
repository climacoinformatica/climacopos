<?php

namespace App\Services\Pagos;

use App\Models\PagoOnline;

/**
 * Contrato de una pasarela de pago.
 *
 * Se define desde el principio aunque solo haya una implementación:
 * muchos salones ya tienen TPV virtual con su banco y querrán Redsys,
 * que además ofrece Bizum nativo. Con la interfaz puesta, añadirlo
 * después es escribir una clase, no reescribir el portal.
 */
interface Pasarela
{
    /**
     * Prepara el pago y devuelve la URL a la que enviar al cliente.
     * No cobra nada todavía.
     */
    public function iniciar(PagoOnline $pago, string $urlExito, string $urlCancelar): string;

    /** Consulta el estado real en la pasarela. */
    public function consultar(PagoOnline $pago): array;

    /** Devolución total o parcial. */
    public function devolver(PagoOnline $pago, ?float $importe = null, ?string $motivo = null): bool;

    /** Comprueba la firma de un webhook. */
    public function verificarFirma(string $cuerpo, string $firma): bool;

    public function nombre(): string;
}
