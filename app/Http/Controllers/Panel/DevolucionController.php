<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCobro;
use App\Services\GestorDevoluciones;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

class DevolucionController extends Controller
{
    public function __construct(
        protected GestorDevoluciones $gestor = new GestorDevoluciones(),
    ) {
    }

    public function mostrar(Ticket $ticket)
    {
        if (! $ticket->esDevolvible()) {
            return redirect()->route('panel.tpv.tickets')
                ->with('error', 'Este documento no admite devolución.');
        }

        return view('panel.tpv.devolucion', [
            'ticket'         => $ticket->load('lineas.articulo', 'cobros'),
            'lineas'         => $this->gestor->disponible($ticket),
            'rectificativas' => $this->gestor->rectificativasDe($ticket),
            'medios'         => SesionSalon::usuario()->mediosPagoPermitidos(),
        ]);
    }

    public function devolver(Request $peticion, Ticket $ticket)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario->tienePermiso(Permisos::TPV_ANULAR_TICKET)) {
            return back()->with('error', 'Tu perfil no permite hacer devoluciones.');
        }

        $datos = $peticion->validate([
            'motivo'       => ['required', 'string', 'max:255'],
            'medio'        => ['required', 'string'],
            'cantidades'   => ['required', 'array'],
            'cantidades.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'motivo.required' => 'Hay que indicar el motivo de la devolución.',
        ]);

        // Solo las líneas con cantidad
        $cantidades = array_filter(
            array_map('floatval', $datos['cantidades']),
            fn ($cantidad) => $cantidad > 0,
        );

        try {
            $rectificativa = $this->gestor->devolver(
                $ticket,
                $cantidades,
                $datos['motivo'],
                $usuario,
            );

            $this->gestor->reembolsar($rectificativa, $datos['medio']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('panel.tpv.tickets')->with('exito',
            'Devolución realizada. Documento ' . $rectificativa->referencia()
            . ' por ' . number_format(abs((float) $rectificativa->total), 2, ',', '.') . ' €.');
    }
}
