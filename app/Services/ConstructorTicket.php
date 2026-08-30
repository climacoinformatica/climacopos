<?php

namespace App\Services;

use App\Models\CierreJornada;
use App\Models\DisenoTicket;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;

/**
 * Convierte un ticket de la base de datos en bytes ESC/POS,
 * aplicando el diseño configurado por la empresa.
 */
class ConstructorTicket
{
    public function __construct(
        protected ?DisenoTicket $diseno = null,
    ) {
        $this->diseno ??= DisenoTicket::activo();
    }

    public function ticket(Ticket $ticket, bool $esCopia = false): string
    {
        $d = $this->diseno;
        $esc = new EscPos($d->columnas);
        $empresa = tenant();

        $esc->inicializar();

        // ---- Logotipo
        if ($d->logo && ($ruta = $this->rutaLogo($d->logo))) {
            $esc->alinear($d->logo_alineacion)->imagen($ruta, $d->logo_ancho_px)->saltos(1);
        }

        // ---- Cabecera configurable
        $esc->centrar();

        foreach ($d->cabecera ?? [] as $fila) {
            $this->pintarFila($esc, $fila);
        }

        // ---- Datos fiscales de la empresa
        $esc->normal()->centrar();

        if ($empresa->razon_social) {
            $esc->linea($empresa->razon_social);
        }

        if ($empresa->nif) {
            $esc->linea('NIF: ' . $empresa->nif);
        }

        if ($empresa->direccion) {
            $esc->linea($empresa->direccion);
        }

        if ($empresa->cp || $empresa->municipio) {
            $esc->linea(trim($empresa->cp . ' ' . $empresa->municipio));
        }

        if ($empresa->telefono) {
            $esc->linea('Tel. ' . $empresa->telefono);
        }

        $esc->saltos(1);

        // ---- AVISO DE FORMACIÓN
        // Un documento de prácticas tiene que gritar que no es una factura.
        if ($ticket->es_formacion) {
            $esc->negrita(true)->invertido(true)
                ->linea(' DOCUMENTO DE FORMACION ')
                ->linea(' SIN VALOR FISCAL ')
                ->invertido(false)->negrita(false)->saltos(1);
        }

        if ($esCopia) {
            $esc->negrita(true)->linea('*** COPIA ***')->negrita(false)->saltos(1);
        }

        // ---- Identificación del documento
        $esc->izquierda()->separador();
        $esc->fila($ticket->referencia(), $ticket->fecha->format('d/m/Y H:i'));

        if ($d->mostrar_cliente && $ticket->cliente) {
            $esc->linea('Cliente: ' . $ticket->cliente->nombreCompleto());
        }

        $esc->separador();

        // ---- Líneas
        foreach ($ticket->lineas as $linea) {
            $cantidad = rtrim(rtrim(number_format((float) $linea->cantidad, 3, ',', ''), '0'), ',');

            $esc->filaLinea(
                $cantidad,
                $linea->descripcion,
                $this->euros((float) $linea->importe)
            );

            // Detalle solo cuando aporta algo
            $detalles = [];

            if ((float) $linea->cantidad != 1.0) {
                $detalles[] = $this->euros((float) $linea->precio) . '/ud';
            }

            if ((float) $linea->dto_pct > 0) {
                $detalles[] = '-' . rtrim(rtrim(number_format((float) $linea->dto_pct, 2, ',', ''), '0'), ',') . '%';
            }

            if ($linea->es_invitacion) {
                $detalles[] = 'INVITACION';
            }

            if ($d->mostrar_profesional && $linea->usuario) {
                $detalles[] = $linea->usuario->alias ?: $linea->usuario->nombre;
            }

            if ($detalles !== []) {
                $esc->linea('     ' . implode(' · ', $detalles));
            }
        }

        $esc->separador();

        // ---- Totales
        if ($d->mostrar_desglose_impuesto) {
            $etiqueta = ($empresa->regimen_fiscal ?? 'IGIC') === 'IVA' ? 'IVA' : 'IGIC';

            $esc->fila('Base imponible', $this->euros((float) $ticket->base));
            $esc->fila($etiqueta, $this->euros((float) $ticket->impuesto));
        }

        $esc->tamano(1, 2)->negrita(true)
            ->fila('TOTAL', $this->euros((float) $ticket->total))
            ->negrita(false)->tamano(1, 1);

        // ---- Cobros
        if ($ticket->cobros->isNotEmpty()) {
            $esc->saltos(1);

            foreach ($ticket->cobros as $cobro) {
                $esc->fila($cobro->nombreMedio(), $this->euros((float) $cobro->importe));

                if ((float) $cobro->cambio > 0) {
                    $esc->fila('  Entregado', $this->euros((float) $cobro->entregado));
                    $esc->fila('  Cambio', $this->euros((float) $cobro->cambio));
                }
            }
        }

        $esc->saltos(1)->centrar();

        // ---- QR de VERI*FACTU (Fase 10)
        if ($d->mostrar_qr_verifactu && $ticket->verifactu_hash && ! $ticket->es_formacion) {
            $esc->qr($this->urlVerifactu($ticket), 5)->saltos(1);
        }

        // ---- QR de la próxima reserva
        if ($d->mostrar_qr_reserva) {
            $esc->linea('Reserva tu proxima cita')
                ->qr(tenant()->urlPortal(), 5)
                ->saltos(1);
        }

        // ---- Pie configurable
        foreach ($d->pie ?? [] as $fila) {
            $this->pintarFila($esc, $fila);
        }

        $esc->normal()->centrar();

        if ($d->texto_legal) {
            $esc->parrafo($d->texto_legal);
        }

        $esc->saltos($d->lineas_finales);

        if ($d->cortar_papel) {
            $esc->cortar();
        }

        // ---- Cajón, si hubo efectivo
        if ($d->abrir_cajon_efectivo && $ticket->cobroEfectivo() > 0 && ! $ticket->es_formacion) {
            $esc->abrirCajon((int) config_terminal('cajon_pin', 2));
        }

        return $esc->salida();
    }

    /** Informe de cierre de jornada. */
    public function cierre(CierreJornada $cierre): string
    {
        $d = $this->diseno;
        $esc = new EscPos($d->columnas);

        $esc->inicializar()->centrar()->negrita(true)->tamano(1, 2)
            ->linea('CIERRE DE JORNADA')
            ->tamano(1, 1)->negrita(false)
            ->linea(tenant('nombre_comercial'))
            ->saltos(1)->izquierda();

        $esc->fila('Desde', $cierre->fecha_ini->format('d/m/Y H:i'));
        $esc->fila('Hasta', $cierre->fecha_fin->format('d/m/Y H:i'));
        $esc->fila('Usuario', $cierre->usuario?->nombre ?? '-');
        $esc->separador();

        $esc->fila('Tickets', (string) $cierre->num_tickets);
        $esc->fila('Base', $this->euros((float) $cierre->total_base));
        $esc->fila('Impuesto', $this->euros((float) $cierre->total_impuesto));
        $esc->negrita(true)->fila('VENTAS', $this->euros((float) $cierre->total_ventas))->negrita(false);
        $esc->fila('Ticket medio', $this->euros($cierre->ticketMedio()));
        $esc->separador();

        $esc->negrita(true)->linea('POR MEDIO DE PAGO')->negrita(false);

        foreach ($cierre->totales_por_medio ?? [] as $medio => $importe) {
            $esc->fila(ucfirst(strtolower($medio)), $this->euros((float) $importe));
        }

        $esc->separador();
        $esc->negrita(true)->linea('ARQUEO DE EFECTIVO')->negrita(false);
        $esc->fila('Fondo inicial', $this->euros((float) $cierre->efectivo_inicial));
        $esc->fila('Debe haber', $this->euros((float) $cierre->efectivo_teorico));
        $esc->fila('Contado', $this->euros((float) $cierre->efectivo_contado));

        $esc->negrita(true)
            ->fila('DESCUADRE', $this->euros((float) $cierre->descuadre))
            ->negrita(false);

        if (! empty($cierre->totales_por_profesional)) {
            $esc->separador()->negrita(true)->linea('POR PROFESIONAL')->negrita(false);

            foreach ($cierre->totales_por_profesional as $nombre => $importe) {
                $esc->fila($nombre, $this->euros((float) $importe));
            }
        }

        if ($cierre->observaciones) {
            $esc->separador()->parrafo($cierre->observaciones);
        }

        return $esc->saltos($d->lineas_finales)->cortar()->salida();
    }

    /**
     * Parte de trabajo por profesional.
     *
     * Va en papel APARTE del cierre a proposito: el cierre lo maneja
     * quien cuadra el efectivo, y este lleva lo que factura cada uno.
     * Con dos papeles se puede dar uno a cada quien sin que nadie vea de
     * mas.
     */
    public function parteTrabajo(array $datos, \Illuminate\Support\Carbon $fecha): string
    {
        $d = $this->diseno;
        $esc = new EscPos($d->columnas);
        $t = $datos['totales'];

        $esc->inicializar()->centrar()->negrita(true)->tamano(1, 2)
            ->linea('PARTE DE TRABAJO')
            ->tamano(1, 1)->negrita(false)
            ->linea(tenant('nombre_comercial'))
            ->linea($fecha->format('d/m/Y'))
            ->saltos(1)->izquierda()->separador();

        if ($datos['filas']->isEmpty()) {
            $esc->centrar()->linea('Sin ventas este dia')->izquierda();

            return $esc->saltos($d->lineas_finales)->cortar()->salida();
        }

        foreach ($datos['filas'] as $fila) {
            $esc->negrita(true)
                ->linea(mb_strtoupper($fila['usuario']->nombre))
                ->negrita(false);

            $esc->fila('  Servicios', (string) $fila['servicios']);

            if ($fila['productos'] > 0) {
                $esc->fila('  Productos', (string) $fila['productos']);
            }

            $esc->fila('  Facturado', $this->euros($fila['facturado']));

            if ($fila['medio'] > 0) {
                $esc->fila('  Ticket medio', $this->euros($fila['medio']));
            }

            if ($fila['comision'] > 0) {
                $esc->negrita(true)
                    ->fila('  Le corresponde', $this->euros($fila['comision']))
                    ->negrita(false);
            }

            $esc->saltos(1);
        }

        $esc->separador();
        $esc->negrita(true)
            ->fila('TOTAL SERVICIOS', (string) $t['servicios'])
            ->fila('TOTAL FACTURADO', $this->euros($t['facturado']));

        if ($t['comisiones'] > 0) {
            $esc->fila('TOTAL COMISIONES', $this->euros($t['comisiones']));
        }

        $esc->negrita(false)->separador();

        /**
         * Las lineas sin ejecutor SI se imprimen.
         *
         * Quien firme el parte tiene que saber que hay importes sin
         * repartir: callarlo haria cuadrar el papel a costa de que
         * alguien cobre de menos.
         */
        if ($t['sin_asignar'] > 0) {
            $esc->negrita(true)->linea('SIN PROFESIONAL ASIGNADO')->negrita(false)
                ->fila('  Lineas', (string) $t['sin_asignar'])
                ->fila('  Importe', $this->euros($t['sin_asignar_imp']))
                ->linea('  No estan repartidas.')
                ->separador();
        }

        $esc->saltos(1)->centrar()
            ->linea('Recoge lo EJECUTADO por cada uno,')
            ->linea('no quien lo cobro.')
            ->linea('No sustituye al cierre de caja.');

        return $esc->saltos($d->lineas_finales)->cortar()->salida();
    }

    /** Ticket de prueba para comprobar la impresora. */
    public function prueba(): string
    {
        $esc = new EscPos($this->diseno->columnas);

        return $esc->inicializar()
            ->centrar()->negrita(true)->tamano(2, 2)
            ->linea('PRUEBA')
            ->tamano(1, 1)->negrita(false)
            ->linea(tenant('nombre_comercial'))
            ->linea(now()->format('d/m/Y H:i:s'))
            ->saltos(1)->izquierda()
            ->separador()
            ->linea('Acentos: aeiou AEIOU nN')
            ->linea('Simbolos: 12,34 EUR')
            ->linea(str_repeat('1234567890', 5))
            ->separador()
            ->fila('Columna izquierda', '99,99')
            ->filaLinea('2', 'Articulo de ejemplo', '44,00')
            ->separador()
            ->centrar()
            ->linea('Codigo QR:')
            ->qr(tenant()->urlPortal(), 5)
            ->saltos(1)
            ->linea('Si lees esto, la impresora')
            ->linea('esta configurada correctamente.')
            ->saltos(4)
            ->cortar()
            ->salida();
    }

    public function aperturaCajon(): string
    {
        return (new EscPos())->abrirCajon((int) config_terminal('cajon_pin', 2))->salida();
    }

    // ------------------------------------------------------------------

    protected function pintarFila(EscPos $esc, array $fila): void
    {
        $esc->alinear($fila['alineacion'] ?? 'CENTRO')
            ->negrita((bool) ($fila['negrita'] ?? false))
            ->tamano(
                ($fila['doble_ancho'] ?? false) ? 2 : 1,
                ($fila['doble_alto'] ?? false) ? 2 : 1,
            )
            ->linea($fila['texto'] ?? '')
            ->normal();
    }

    protected function euros(float $importe): string
    {
        return number_format($importe, 2, ',', '.') . ' E';
    }

    protected function rutaLogo(string $logo): ?string
    {
        $ruta = Storage::disk('public')->path($logo);

        return is_readable($ruta) ? $ruta : null;
    }

    protected function urlVerifactu(Ticket $ticket): string
    {
        // Se completará en la Fase 10 con la URL oficial de la AEAT
        return 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?hash=' . $ticket->verifactu_hash;
    }
}
