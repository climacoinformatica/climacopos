<?php

namespace App\Services\Verifactu;

use App\Models\VerifactuRegistro;

/**
 * Cálculo de la huella (hash) de un registro de facturación.
 *
 * Es el corazón del reglamento: cada registro incluye la huella del
 * anterior, de modo que la secuencia queda encadenada. Modificar o
 * borrar un registro rompe todos los siguientes, y eso es detectable.
 *
 * ─────────────────────────────────────────────────────────────────────
 *  AVISO IMPORTANTE
 *
 *  El orden de los campos, sus nombres y su formato están fijados por la
 *  especificación técnica de la AEAT. NO se pueden cambiar «porque queda
 *  más ordenado»: cualquier variación produce una huella distinta y la
 *  Agencia rechaza el registro.
 *
 *  Antes de pasar a producción hay que contrastar este formato con la
 *  versión vigente de la especificación, que la AEAT ha ido revisando.
 * ─────────────────────────────────────────────────────────────────────
 */
class HuellaVerifactu
{
    /**
     * Huella de un registro de ALTA.
     *
     * Cadena a hashear, en este orden exacto:
     *   IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura,
     *   TipoFactura, CuotaTotal, ImporteTotal, Huella (la anterior),
     *   FechaHoraHusoGenRegistro
     */
    public static function alta(array $datos): string
    {
        $cadena = 'IDEmisorFactura=' . self::limpiar($datos['nif_emisor'])
            . '&NumSerieFactura=' . self::limpiar($datos['serie_numero'])
            . '&FechaExpedicionFactura=' . self::limpiar($datos['fecha_expedicion'])
            . '&TipoFactura=' . self::limpiar($datos['tipo_factura'])
            . '&CuotaTotal=' . self::importe($datos['cuota'])
            . '&ImporteTotal=' . self::importe($datos['total'])
            . '&Huella=' . self::limpiar($datos['huella_anterior'] ?? '')
            . '&FechaHoraHusoGenRegistro=' . self::limpiar($datos['fecha_hora_huso']);

        return self::hash($cadena);
    }

    /**
     * Huella de un registro de ANULACION.
     * Lleva menos campos: no hay importes que anular, solo la referencia.
     */
    public static function anulacion(array $datos): string
    {
        $cadena = 'IDEmisorFacturaAnulada=' . self::limpiar($datos['nif_emisor'])
            . '&NumSerieFacturaAnulada=' . self::limpiar($datos['serie_numero'])
            . '&FechaExpedicionFacturaAnulada=' . self::limpiar($datos['fecha_expedicion'])
            . '&Huella=' . self::limpiar($datos['huella_anterior'] ?? '')
            . '&FechaHoraHusoGenRegistro=' . self::limpiar($datos['fecha_hora_huso']);

        return self::hash($cadena);
    }

    /**
     * SHA-256 en hexadecimal MAYÚSCULAS.
     *
     * Las mayúsculas no son un capricho: la AEAT compara la cadena tal
     * cual, y en minúsculas el registro se rechaza.
     */
    protected static function hash(string $cadena): string
    {
        return strtoupper(hash('sha256', $cadena));
    }

    /**
     * Los campos se recortan de espacios por los extremos, pero NO se
     * normalizan por dentro: la AEAT espera el valor tal cual figura en
     * el registro.
     */
    protected static function limpiar(?string $valor): string
    {
        return trim((string) $valor);
    }

    /**
     * Importes con dos decimales y punto como separador.
     *
     * Ojo con el signo: −0,00 no existe. Un importe que redondee a cero
     * negativo debe escribirse «0.00», o la huella no coincide.
     */
    protected static function importe(float|string $valor): string
    {
        $numero = round((float) $valor, 2);

        if ($numero == 0.0) {
            $numero = 0.0;   // elimina el −0
        }

        return number_format($numero, 2, '.', '');
    }

    /**
     * Fecha y hora con huso horario, en formato ISO 8601.
     * Ejemplo: 2026-08-23T14:30:15+01:00
     *
     * Se genera UNA vez y se guarda: recalcularla al enviar daría otra
     * huella y el registro sería inválido.
     */
    public static function marcaTemporal(?\DateTimeInterface $momento = null): string
    {
        $momento ??= now();

        return $momento instanceof \Illuminate\Support\Carbon
            ? $momento->format('Y-m-d\TH:i:sP')
            : \Illuminate\Support\Carbon::instance(
                \DateTime::createFromInterface($momento)
            )->format('Y-m-d\TH:i:sP');
    }

    /**
     * Comprueba que la cadena de un salón no está rota.
     *
     * @return array{integra: bool, roto_en: ?int, revisados: int}
     */
    public static function verificarCadena(): array
    {
        $anterior = null;
        $revisados = 0;

        foreach (VerifactuRegistro::orderBy('id')->cursor() as $registro) {
            $revisados++;

            // El primero de la cadena no tiene anterior
            $huellaEsperada = $anterior?->huella;

            if ($registro->huella_anterior !== $huellaEsperada) {
                return ['integra' => false, 'roto_en' => $registro->id, 'revisados' => $revisados];
            }

            // Se recalcula la huella con los datos guardados
            $datos = [
                'nif_emisor'       => $registro->nif_emisor,
                'serie_numero'     => $registro->serie_numero,
                'fecha_expedicion' => $registro->fecha_expedicion->format('d-m-Y'),
                'tipo_factura'     => $registro->tipo_factura,
                'cuota'            => (float) $registro->cuota,
                'total'            => (float) $registro->total,
                'huella_anterior'  => $registro->huella_anterior,
                'fecha_hora_huso'  => $registro->fecha_hora_huso,
            ];

            $recalculada = $registro->tipo === 'ALTA'
                ? self::alta($datos)
                : self::anulacion($datos);

            if ($recalculada !== $registro->huella) {
                return ['integra' => false, 'roto_en' => $registro->id, 'revisados' => $revisados];
            }

            $anterior = $registro;
        }

        return ['integra' => true, 'roto_en' => null, 'revisados' => $revisados];
    }
}
