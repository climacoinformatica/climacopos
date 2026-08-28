<?php

namespace App\Services\Verifactu;

use App\Models\VerifactuRegistro;

/**
 * Genera el XML del registro de facturación.
 *
 * ─────────────────────────────────────────────────────────────────────
 *  AVISO
 *
 *  La estructura, los espacios de nombres y el orden de los elementos
 *  vienen fijados por el esquema XSD de la AEAT. Un elemento fuera de
 *  orden hace que el envío se rechace con un error de validación, no
 *  con un mensaje claro.
 *
 *  Antes de producción hay que validar el XML contra el XSD oficial
 *  vigente, que puede descargarse de la sede electrónica.
 * ─────────────────────────────────────────────────────────────────────
 */
class GeneradorXml
{
    protected const NS_SF  = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    protected const NS_SUM = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    public function registro(VerifactuRegistro $registro): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $raiz = $xml->createElementNS(self::NS_SUM, 'sum:RegFactuSistemaFacturacion');
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::NS_SF);
        $xml->appendChild($raiz);

        // ---- Cabecera: quién declara
        $cabecera = $xml->createElementNS(self::NS_SUM, 'sum:Cabecera');
        $obligado = $xml->createElementNS(self::NS_SF, 'sf:ObligadoEmision');

        $empresa = tenant();

        $this->hijo($xml, $obligado, self::NS_SF, 'sf:NombreRazon',
            mb_substr($empresa->razon_social ?: $empresa->nombre_comercial, 0, 120));
        $this->hijo($xml, $obligado, self::NS_SF, 'sf:NIF', $registro->nif_emisor);

        $cabecera->appendChild($obligado);
        $raiz->appendChild($cabecera);

        // ---- El registro en sí
        $envio = $xml->createElementNS(self::NS_SUM, 'sum:RegistroFactura');

        $envio->appendChild($registro->tipo === 'ALTA'
            ? $this->registroAlta($xml, $registro)
            : $this->registroAnulacion($xml, $registro));

        $raiz->appendChild($envio);

        return $xml->saveXML();
    }

    protected function registroAlta(\DOMDocument $xml, VerifactuRegistro $r): \DOMElement
    {
        $alta = $xml->createElementNS(self::NS_SF, 'sf:RegistroAlta');

        $this->hijo($xml, $alta, self::NS_SF, 'sf:IDVersion', '1.0');

        // Identificación de la factura
        $id = $xml->createElementNS(self::NS_SF, 'sf:IDFactura');
        $this->hijo($xml, $id, self::NS_SF, 'sf:IDEmisorFactura', $r->nif_emisor);
        $this->hijo($xml, $id, self::NS_SF, 'sf:NumSerieFactura', $r->serie_numero);
        $this->hijo($xml, $id, self::NS_SF, 'sf:FechaExpedicionFactura',
            $r->fecha_expedicion->format('d-m-Y'));
        $alta->appendChild($id);

        $this->hijo($xml, $alta, self::NS_SF, 'sf:NombreRazonEmisor',
            mb_substr(tenant()->razon_social ?: tenant()->nombre_comercial, 0, 120));
        $this->hijo($xml, $alta, self::NS_SF, 'sf:TipoFactura', $r->tipo_factura);

        /**
         * Datos de la rectificacion.
         *
         * Si el tipo empieza por R, la AEAT exige saber a que factura
         * rectifica y con que criterio. Se usa 'I' (por diferencias): el
         * documento declara solo el importe que se corrige, no repite la
         * factura entera. Es lo que encaja con una devolucion parcial.
         */
        if (str_starts_with($r->tipo_factura, 'R')) {
            $this->hijo($xml, $alta, self::NS_SF, 'sf:TipoRectificativa', 'I');

            $original = $r->ticket?->rectificaA;

            if ($original) {
                $rectificadas = $xml->createElementNS(self::NS_SF, 'sf:FacturasRectificadas');
                $idFactura = $xml->createElementNS(self::NS_SF, 'sf:IDFacturaRectificada');

                $this->hijo($xml, $idFactura, self::NS_SF, 'sf:IDEmisorFactura', $r->nif_emisor);
                $this->hijo($xml, $idFactura, self::NS_SF, 'sf:NumSerieFactura', $original->referencia());
                $this->hijo($xml, $idFactura, self::NS_SF, 'sf:FechaExpedicionFactura',
                    $original->fecha->format('d-m-Y'));

                $rectificadas->appendChild($idFactura);
                $alta->appendChild($rectificadas);
            }
        }

        $this->hijo($xml, $alta, self::NS_SF, 'sf:DescripcionOperacion',
            mb_substr($r->descripcion ?: 'Servicios', 0, 500));

        // Desglose de impuestos
        $desglose = $xml->createElementNS(self::NS_SF, 'sf:Desglose');
        $detalle = $xml->createElementNS(self::NS_SF, 'sf:DetalleDesglose');

        // IGIC en Canarias, IVA en península
        $this->hijo($xml, $detalle, self::NS_SF, 'sf:Impuesto',
            (tenant()->regimen_fiscal ?? 'IGIC') === 'IGIC' ? '03' : '01');
        $this->hijo($xml, $detalle, self::NS_SF, 'sf:ClaveRegimen', '01');
        $this->hijo($xml, $detalle, self::NS_SF, 'sf:CalificacionOperacion', 'S1');
        $this->hijo($xml, $detalle, self::NS_SF, 'sf:TipoImpositivo', $this->numero($r->tipo_impositivo));
        $this->hijo($xml, $detalle, self::NS_SF, 'sf:BaseImponibleOimporteNoSujeto', $this->numero($r->base));
        $this->hijo($xml, $detalle, self::NS_SF, 'sf:CuotaRepercutida', $this->numero($r->cuota));

        $desglose->appendChild($detalle);
        $alta->appendChild($desglose);

        $this->hijo($xml, $alta, self::NS_SF, 'sf:CuotaTotal', $this->numero($r->cuota));
        $this->hijo($xml, $alta, self::NS_SF, 'sf:ImporteTotal', $this->numero($r->total));

        // ---- Encadenamiento
        $encadenado = $xml->createElementNS(self::NS_SF, 'sf:Encadenamiento');

        if (blank($r->huella_anterior)) {
            // El primero de la cadena lo declara explícitamente
            $this->hijo($xml, $encadenado, self::NS_SF, 'sf:PrimerRegistro', 'S');
        } else {
            $previo = $xml->createElementNS(self::NS_SF, 'sf:RegistroAnterior');
            $anterior = $r->registro_anterior_id
                ? VerifactuRegistro::find($r->registro_anterior_id) : null;

            $this->hijo($xml, $previo, self::NS_SF, 'sf:IDEmisorFactura',
                $anterior?->nif_emisor ?? $r->nif_emisor);
            $this->hijo($xml, $previo, self::NS_SF, 'sf:NumSerieFactura',
                $anterior?->serie_numero ?? '');
            $this->hijo($xml, $previo, self::NS_SF, 'sf:FechaExpedicionFactura',
                $anterior?->fecha_expedicion->format('d-m-Y') ?? '');
            $this->hijo($xml, $previo, self::NS_SF, 'sf:Huella', $r->huella_anterior);

            $encadenado->appendChild($previo);
        }

        $alta->appendChild($encadenado);

        // ---- Qué programa lo generó
        $alta->appendChild($this->sistemaInformatico($xml));

        $this->hijo($xml, $alta, self::NS_SF, 'sf:FechaHoraHusoGenRegistro', $r->fecha_hora_huso);
        $this->hijo($xml, $alta, self::NS_SF, 'sf:TipoHuella', '01');   // SHA-256
        $this->hijo($xml, $alta, self::NS_SF, 'sf:Huella', $r->huella);

        return $alta;
    }

    protected function registroAnulacion(\DOMDocument $xml, VerifactuRegistro $r): \DOMElement
    {
        $anulacion = $xml->createElementNS(self::NS_SF, 'sf:RegistroAnulacion');

        $this->hijo($xml, $anulacion, self::NS_SF, 'sf:IDVersion', '1.0');

        $id = $xml->createElementNS(self::NS_SF, 'sf:IDFactura');
        $this->hijo($xml, $id, self::NS_SF, 'sf:IDEmisorFacturaAnulada', $r->nif_emisor);
        $this->hijo($xml, $id, self::NS_SF, 'sf:NumSerieFacturaAnulada', $r->serie_numero);
        $this->hijo($xml, $id, self::NS_SF, 'sf:FechaExpedicionFacturaAnulada',
            $r->fecha_expedicion->format('d-m-Y'));
        $anulacion->appendChild($id);

        $encadenado = $xml->createElementNS(self::NS_SF, 'sf:Encadenamiento');

        if (blank($r->huella_anterior)) {
            $this->hijo($xml, $encadenado, self::NS_SF, 'sf:PrimerRegistro', 'S');
        } else {
            $previo = $xml->createElementNS(self::NS_SF, 'sf:RegistroAnterior');
            $anterior = $r->registro_anterior_id
                ? VerifactuRegistro::find($r->registro_anterior_id) : null;

            $this->hijo($xml, $previo, self::NS_SF, 'sf:IDEmisorFactura',
                $anterior?->nif_emisor ?? $r->nif_emisor);
            $this->hijo($xml, $previo, self::NS_SF, 'sf:NumSerieFactura',
                $anterior?->serie_numero ?? '');
            $this->hijo($xml, $previo, self::NS_SF, 'sf:FechaExpedicionFactura',
                $anterior?->fecha_expedicion->format('d-m-Y') ?? '');
            $this->hijo($xml, $previo, self::NS_SF, 'sf:Huella', $r->huella_anterior);

            $encadenado->appendChild($previo);
        }

        $anulacion->appendChild($encadenado);
        $anulacion->appendChild($this->sistemaInformatico($xml));

        $this->hijo($xml, $anulacion, self::NS_SF, 'sf:FechaHoraHusoGenRegistro', $r->fecha_hora_huso);
        $this->hijo($xml, $anulacion, self::NS_SF, 'sf:TipoHuella', '01');
        $this->hijo($xml, $anulacion, self::NS_SF, 'sf:Huella', $r->huella);

        return $anulacion;
    }

    /**
     * Datos del sistema informático de facturación.
     *
     * La AEAT quiere saber qué programa generó el registro, quién lo
     * desarrolla y en qué versión. Es lo que permite perseguir al
     * fabricante de un software de doble uso.
     */
    protected function sistemaInformatico(\DOMDocument $xml): \DOMElement
    {
        $sistema = $xml->createElementNS(self::NS_SF, 'sf:SistemaInformatico');
        $config = config('verifactu.sistema');

        $this->hijo($xml, $sistema, self::NS_SF, 'sf:NombreRazon', $config['nombre_razon']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:NIF', $config['nif']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:NombreSistemaInformatico', $config['nombre']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:IdSistemaInformatico', $config['id']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:Version', $config['version']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:NumeroInstalacion', $config['numero_instalacion']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:TipoUsoPosibleSoloVerifactu', $config['solo_verifactu']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:TipoUsoPosibleMultiOT', $config['multi_ot']);
        $this->hijo($xml, $sistema, self::NS_SF, 'sf:IndicadorMultiplesOT', $config['indicador_multi_ot']);

        return $sistema;
    }

    protected function hijo(\DOMDocument $xml, \DOMElement $padre, string $ns, string $nombre, string $valor): void
    {
        $padre->appendChild($xml->createElementNS($ns, $nombre, htmlspecialchars($valor, ENT_XML1)));
    }

    protected function numero(float|string $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
