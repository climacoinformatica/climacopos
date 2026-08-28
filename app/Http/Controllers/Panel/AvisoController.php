<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Reserva;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

class AvisoController extends Controller
{
    /**
     * Sondeo ligero cada 10 segundos.
     * Devuelve solo el contador y una huella; el panel pide el detalle
     * unicamente cuando la huella cambia.
     */
    public function contador()
    {
        $activos = Aviso::queDestellan()->count();

        return response()->json([
            'pendientes' => Reserva::pendientes()->count(),
            'avisos'     => $activos,
            'huella'     => Aviso::huella(),
        ]);
    }

    public function lista()
    {
        $avisos = Aviso::queDestellan()
            ->orderByDesc('requiere_accion')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json(
            $avisos->map(function (Aviso $aviso) {
                $reserva = $aviso->tipo === 'RESERVA_NUEVA' ? $aviso->reserva : null;

                return [
                    'id'      => $aviso->id,
                    'tipo'    => $aviso->tipo,
                    'icono'   => $aviso->icono(),
                    'titulo'  => $aviso->titulo,
                    'mensaje' => $aviso->mensaje,
                    'accion'  => $aviso->requiere_accion,
                    'hace'    => $aviso->created_at->diffForHumans(short: true),
                    'reserva' => $reserva ? [
                        'id'        => $reserva->id,
                        'codigo'    => $reserva->codigo,
                        'url'       => route('panel.agenda.cita', $reserva),
                        'telefono'  => $reserva->cliente_telefono,
                        'importe'   => number_format((float) $reserva->importe_total, 2, ',', '.'),
                        'pendiente' => $reserva->estado === 'PENDIENTE',
                    ] : null,
                ];
            })
        );
    }

    public function marcarLeido(Aviso $aviso)
    {
        // Los que exigen accion no se apagan leyendolos: se apagan
        // resolviendo la reserva.
        if (! $aviso->requiere_accion) {
            $aviso->update(['resuelto' => true]);
        }

        $aviso->marcarLeido(SesionSalon::usuario());

        return response()->json(['ok' => true, 'huella' => Aviso::huella()]);
    }

    /** Confirmar o rechazar sin salir de la pantalla en la que estas. */
    public function resolverReserva(Request $peticion, Reserva $reserva)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario?->tienePermiso(Permisos::RESERVAS_CONFIRMAR)) {
            return response()->json(['error' => 'Tu perfil no permite confirmar reservas.'], 403);
        }

        $datos = $peticion->validate([
            'accion' => ['required', 'in:confirmar,rechazar'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $ok = $datos['accion'] === 'confirmar'
            ? $reserva->confirmar($usuario)
            : $reserva->rechazar($datos['motivo'] ?? 'Sin disponibilidad', $usuario);

        if ($ok) {
            Aviso::resolverDeReserva($reserva->id);
        }

        return response()->json([
            'ok'     => $ok,
            'estado' => $reserva->fresh()->estado,
            'huella' => Aviso::huella(),
        ], $ok ? 200 : 422);
    }
}
