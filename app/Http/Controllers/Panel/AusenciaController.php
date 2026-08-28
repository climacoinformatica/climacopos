<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Ausencia;
use App\Models\Usuario;
use App\Services\GestorAusencias;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

class AusenciaController extends Controller
{
    public function __construct(
        protected GestorAusencias $gestor = new GestorAusencias(),
    ) {
    }

    /** Mis ausencias: lo que ve cada persona de si misma. */
    public function index()
    {
        $usuario = SesionSalon::usuario();

        return view('panel.ausencias.index', [
            'usuario'   => $usuario,
            'cupo'      => $this->gestor->cupo($usuario),
            'ausencias' => Ausencia::where('usuario_id', $usuario->id)
                                ->orderByDesc('desde')->limit(30)->get(),
            'gestiona'  => $usuario->tienePermiso(Permisos::USUARIOS_GESTIONAR),
            'pendientes'=> $usuario->tienePermiso(Permisos::USUARIOS_GESTIONAR)
                           ? Ausencia::pendientes()->with('usuario')->orderBy('desde')->get()
                           : collect(),
        ]);
    }

    public function solicitar(Request $peticion)
    {
        $datos = $peticion->validate([
            'tipo'       => ['required', 'in:' . implode(',', array_keys(Ausencia::TIPOS))],
            'desde'      => ['required', 'date'],
            'hasta'      => ['required', 'date', 'after_or_equal:desde'],
            'medio_dia'  => ['nullable', 'in:MANANA,TARDE'],
            'motivo'     => ['nullable', 'string', 'max:300'],
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
        ]);

        $solicitante = SesionSalon::usuario();

        /**
         * Un responsable puede registrar la ausencia de otra persona:
         * una baja medica no la mete quien esta en cama.
         */
        $paraQuien = $solicitante;

        if (! empty($datos['usuario_id'])
            && $solicitante->tienePermiso(Permisos::USUARIOS_GESTIONAR)) {
            $paraQuien = Usuario::findOrFail($datos['usuario_id']);
        }

        try {
            $ausencia = $this->gestor->solicitar(
                $paraQuien,
                $datos['tipo'],
                $datos['desde'],
                $datos['hasta'],
                $datos['motivo'] ?? null,
                $datos['medio_dia'] ?? null,
            );

            // Si lo registra un responsable, queda aprobado directamente
            if ($solicitante->tienePermiso(Permisos::USUARIOS_GESTIONAR)) {
                $this->gestor->aprobar($ausencia, $solicitante);

                return back()->with('exito', 'Ausencia registrada y aprobada.');
            }
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('exito',
            'Solicitud enviada. Te avisaremos cuando la revisen.');
    }

    public function aprobar(Request $peticion, Ausencia $ausencia)
    {
        try {
            $this->gestor->aprobar(
                $ausencia,
                SesionSalon::usuario(),
                $peticion->input('respuesta'),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito',
            'Aprobada. La agenda ya no ofrecera huecos esos dias.');
    }

    public function rechazar(Request $peticion, Ausencia $ausencia)
    {
        $peticion->validate([
            'respuesta' => ['required', 'string', 'max:300'],
        ], [
            'respuesta.required' => 'Explica por que se rechaza.',
        ]);

        try {
            $this->gestor->rechazar($ausencia, SesionSalon::usuario(),
                $peticion->input('respuesta'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Solicitud rechazada.');
    }

    public function cancelar(Request $peticion, Ausencia $ausencia)
    {
        $usuario = SesionSalon::usuario();

        $esSuya = $ausencia->usuario_id === $usuario->id;

        if (! $esSuya && ! $usuario->tienePermiso(Permisos::USUARIOS_GESTIONAR)) {
            return back()->with('error', 'Solo puedes cancelar tus propias ausencias.');
        }

        $this->gestor->cancelar($ausencia, $usuario, $peticion->input('motivo'));

        return back()->with('exito', 'Ausencia cancelada. Esos dias vuelven a estar disponibles.');
    }

    /** Calendario del equipo. */
    public function calendario(Request $peticion)
    {
        $ano = (int) $peticion->input('ano', now()->year);
        $mes = (int) $peticion->input('mes', now()->month);

        return view('panel.ausencias.calendario', [
            'calendario' => $this->gestor->calendario($ano, $mes),
            'ano'        => $ano,
            'mes'        => $mes,
            'cupos'      => Usuario::activos()->get()
                                ->mapWithKeys(fn ($u) => [$u->id => $this->gestor->cupo($u, $ano)]),
        ]);
    }
}
