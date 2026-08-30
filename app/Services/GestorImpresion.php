<?php

namespace App\Services;

use App\Models\CierreJornada;
use App\Models\ColaImpresion;
use App\Models\Terminal;
use App\Models\Ticket;
use App\Support\SesionSalon;

class GestorImpresion
{
    public function __construct(
        protected ConstructorTicket $constructor = new ConstructorTicket(),
    ) {
    }

    public function ticket(Ticket $ticket, ?Terminal $terminal = null, bool $esCopia = false): ?ColaImpresion
    {
        $terminal ??= SesionSalon::terminal();

        if (! $terminal) {
            return null;
        }

        return $this->encolar($terminal, $esCopia ? 'COPIA' : 'TICKET', 'TICKETS',
            $this->constructor->ticket($ticket, $esCopia),
            $ticket->referencia() . ($esCopia ? ' (copia)' : ''),
            $ticket->id,
        );
    }

    public function cierre(CierreJornada $cierre, ?Terminal $terminal = null): ?ColaImpresion
    {
        $terminal ??= SesionSalon::terminal();

        if (! $terminal) {
            return null;
        }

        return $this->encolar($terminal, 'CIERRE', 'TICKETS',
            $this->constructor->cierre($cierre),
            'Cierre del ' . $cierre->fecha_fin->format('d/m/Y'),
            $cierre->id,
        );
    }

    /**
     * Parte de trabajo por profesional.
     *
     * Va en papel APARTE del cierre: el cierre lo maneja quien cuadra el
     * efectivo, y este lleva lo que factura cada uno. Dos papeles
     * permiten dar uno a cada quien sin que nadie vea de mas.
     */
    public function parteTrabajo($fecha, ?Terminal $terminal = null): ?ColaImpresion
    {
        $terminal ??= SesionSalon::terminal();

        if (! $terminal) {
            return null;
        }

        $fecha = $fecha instanceof \Illuminate\Support\Carbon
            ? $fecha
            : \Illuminate\Support\Carbon::parse($fecha);

        $datos = (new GestorProduccion())->delDia($fecha);

        return $this->encolar($terminal, 'PARTE', 'TICKETS',
            $this->constructor->parteTrabajo($datos, $fecha),
            'Parte de trabajo del ' . $fecha->format('d/m/Y'),
        );
    }

    public function prueba(Terminal $terminal): ColaImpresion
    {
        return $this->encolar($terminal, 'PRUEBA', 'TICKETS',
            $this->constructor->prueba(), 'Ticket de prueba');
    }

    public function abrirCajon(?Terminal $terminal = null): ?ColaImpresion
    {
        $terminal ??= SesionSalon::terminal();

        if (! $terminal) {
            return null;
        }

        return $this->encolar($terminal, 'CAJON', 'CAJON',
            $this->constructor->aperturaCajon(), 'Apertura de cajón');
    }

    /**
     * Texto para el visor de cliente.
     * Dos líneas de 20 caracteres, que es lo habitual en estos aparatos.
     */
    public function visor(string $linea1, string $linea2 = '', ?Terminal $terminal = null): ?ColaImpresion
    {
        $terminal ??= SesionSalon::terminal();

        if (! $terminal) {
            return null;
        }

        $texto = str_pad(mb_substr($linea1, 0, 20), 20)
               . str_pad(mb_substr($linea2, 0, 20), 20);

        return $this->encolar($terminal, 'VISOR', 'VISOR', $texto, $linea1);
    }

    protected function encolar(
        Terminal $terminal,
        string $tipo,
        string $destino,
        string $payload,
        ?string $descripcion = null,
        ?int $referenciaId = null,
    ): ColaImpresion {
        return ColaImpresion::create([
            'terminal_id'   => $terminal->id,
            'tipo'          => $tipo,
            'destino'       => $destino,
            'payload'       => base64_encode($payload),
            'descripcion'   => $descripcion,
            'referencia_id' => $referenciaId,
            'usuario_id'    => SesionSalon::usuario()?->id,
            'estado'        => 'PENDIENTE',
        ]);
    }
}
