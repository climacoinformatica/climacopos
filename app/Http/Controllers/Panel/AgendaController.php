<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\BloqueoAgenda;
use App\Models\Cliente;
use App\Models\Familia;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Services\GestorReservas;
use App\Services\MotorHuecos;
use App\Support\Intervalo;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    public function __construct(
        protected MotorHuecos $motor = new MotorHuecos(),
        protected GestorReservas $gestor = new GestorReservas(),
    ) {
    }

    public function dia(Request $peticion)
    {
        $fecha = $peticion->filled('fecha')
            ? Carbon::parse($peticion->string('fecha')->toString())
            : Carbon::today();

        $usuario = SesionSalon::usuario();

        // Sin permiso para ver todas, solo se ve la propia
        $profesionales = $usuario->tienePermiso(\App\Support\Permisos::AGENDA_VER_TODAS)
            ? Usuario::activos()->profesionales()->orderBy('orden')->orderBy('nombre')->get()
            : Usuario::where('id', $usuario->id)->get();

        return view('panel.agenda.dia', [
            'fecha'         => $fecha,
            'profesionales' => $profesionales,
            'columnas'      => $this->montarColumnas($fecha, $profesionales),
            'horaIni'       => (string) config_empresa('agenda_hora_ini', '08:00'),
            'horaFin'       => (string) config_empresa('agenda_hora_fin', '21:00'),
            'pendientes'    => Reserva::pendientes()->count(),
        ]);
    }

    /** Datos de cada columna: citas colocadas y tramos fuera de horario. */
    protected function montarColumnas(Carbon $fecha, $profesionales): array
    {
        $columnas = [];

        $reservas = Reserva::with(['lineas.articulo', 'cliente'])
            ->delDia($fecha)
            ->whereNotIn('estado', ['CANCELADA', 'RECHAZADA'])
            ->get();

        $bloqueos = BloqueoAgenda::delDia($fecha)->get();

        foreach ($profesionales as $profesional) {
            $jornada = $this->motor->jornadaDe($profesional, $fecha);

            $citas = [];

            foreach ($reservas as $reserva) {
                foreach ($reserva->lineas as $linea) {
                    if ($linea->usuario_id !== $profesional->id) {
                        continue;
                    }

                    $citas[] = [
                        'reserva'  => $reserva,
                        'linea'    => $linea,
                        'ini'      => Intervalo::aMinutos($linea->hora_ini),
                        'duracion' => $linea->duracionTotal(),
                        'pausa'    => (int) $linea->tiempo_pausa_min,
                        'activa'   => (int) $linea->duracion_min,
                    ];
                }
            }

            $columnas[] = [
                'profesional' => $profesional,
                'jornada'     => $jornada,
                'citas'       => $citas,
                'bloqueos'    => $bloqueos->filter(
                    fn ($b) => is_null($b->usuario_id) || $b->usuario_id === $profesional->id
                )->values(),
                'ocupacion'   => $this->porcentajeOcupacion($jornada, $citas),
            ];
        }

        return $columnas;
    }

    /** Porcentaje de la jornada realmente vendido. El dato que más mira un dueño. */
    protected function porcentajeOcupacion(array $jornada, array $citas): int
    {
        $disponible = array_sum(array_map(fn ($t) => $t->duracion(), $jornada));

        if ($disponible === 0) {
            return 0;
        }

        // Solo el tiempo activo: la pausa no se cuenta como ocupada
        $ocupado = array_sum(array_map(fn ($c) => $c['duracion'] - $c['pausa'], $citas));

        return (int) round(($ocupado / $disponible) * 100);
    }

    // ------------------------------------------------------------------
    // Huecos disponibles (para el formulario de cita)
    // ------------------------------------------------------------------

    public function huecos(Request $peticion)
    {
        $peticion->validate([
            'fecha'       => ['required', 'date'],
            'articulo_id' => ['required', 'exists:articulos,id'],
            'usuario_id'  => ['nullable', 'exists:usuarios,id'],
        ]);

        $fecha    = Carbon::parse($peticion->string('fecha')->toString());
        $servicio = Articulo::findOrFail($peticion->integer('articulo_id'));
        $profesional = $peticion->filled('usuario_id')
            ? Usuario::find($peticion->integer('usuario_id'))
            : null;

        // Desde el panel no aplica la antelación mínima: el salón puede
        // meter una cita para dentro de diez minutos si le da la gana.
        $mapa = $this->motor->huecosConProfesional($fecha, $servicio, $profesional, desdePortal: false);

        return response()->json([
            'huecos'   => array_keys($mapa),
            'detalle'  => $mapa,
            'duracion' => $servicio->duracionTotal($profesional),
        ]);
    }

    // ------------------------------------------------------------------
    // Citas
    // ------------------------------------------------------------------

    public function nuevaCita(Request $peticion)
    {
        return view('panel.agenda.cita-form', [
            'reserva'       => new Reserva(),
            'fecha'         => $peticion->filled('fecha')
                                ? Carbon::parse($peticion->string('fecha')->toString())
                                : Carbon::today(),
            'horaSugerida'  => $peticion->string('hora')->toString(),
            'usuarioId'     => $peticion->integer('usuario_id') ?: null,
            'familias'      => Familia::activas()->deServicios()->with('articulos')->orderBy('orden')->get(),
            'profesionales' => Usuario::activos()->profesionales()->orderBy('nombre')->get(),
        ]);
    }

    public function guardarCita(Request $peticion)
    {
        $datos = $peticion->validate([
            'fecha'                  => ['required', 'date'],
            'hora'                   => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'servicios'              => ['required', 'array', 'min:1'],
            'servicios.*.articulo_id'=> ['required', 'exists:articulos,id'],
            'servicios.*.usuario_id' => ['nullable', 'exists:usuarios,id'],
            'cliente_id'             => ['nullable', 'exists:clientes,id'],
            'cliente_nombre'         => ['required_without:cliente_id', 'nullable', 'string', 'max:120'],
            'cliente_telefono'       => ['nullable', 'string', 'max:20'],
            'cliente_email'          => ['nullable', 'email', 'max:160'],
            'notas'                  => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $reserva = $this->gestor->crear(
                Carbon::parse($datos['fecha']),
                $datos['hora'],
                $datos['servicios'],
                [
                    'cliente_id' => $datos['cliente_id'] ?? null,
                    'nombre'     => $datos['cliente_nombre'] ?? null,
                    'telefono'   => $datos['cliente_telefono'] ?? null,
                    'email'      => $datos['cliente_email'] ?? null,
                    'notas'      => $datos['notas'] ?? null,
                ],
                origen: 'LOCAL',
                creadaPor: SesionSalon::usuario(),
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('panel.agenda', ['fecha' => $reserva->fecha->toDateString()])
            ->with('exito', "Cita {$reserva->codigo} creada.");
    }

    public function verCita(Reserva $reserva)
    {
        $reserva->load(['lineas.articulo', 'lineas.usuario', 'cliente']);

        return view('panel.agenda.cita-ver', ['reserva' => $reserva]);
    }

    public function cambiarEstado(Request $peticion, Reserva $reserva)
    {
        $peticion->validate([
            'accion' => ['required', 'in:confirmar,rechazar,cancelar,no_show,atendida,en_curso'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $usuario = SesionSalon::usuario();
        $accion  = $peticion->string('accion')->toString();

        $ok = match ($accion) {
            'confirmar' => $reserva->confirmar($usuario),
            'rechazar'  => $reserva->rechazar($peticion->string('motivo')->toString() ?: 'Sin motivo', $usuario),
            'cancelar'  => $reserva->cancelar('SALON'),
            'no_show'   => $reserva->marcarNoShow(),
            'atendida'  => $reserva->marcarAtendida(),
            'en_curso'  => $reserva->update(['estado' => 'EN_CURSO']),
        };

        return back()->with(
            $ok ? 'exito' : 'error',
            $ok ? 'Cita actualizada.' : 'No se puede aplicar esa acción en el estado actual.'
        );
    }

    public function moverCita(Request $peticion, Reserva $reserva)
    {
        $datos = $peticion->validate([
            'fecha'      => ['required', 'date'],
            'hora'       => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
        ]);

        try {
            $this->gestor->mover(
                $reserva,
                Carbon::parse($datos['fecha']),
                $datos['hora'],
                isset($datos['usuario_id']) ? Usuario::find($datos['usuario_id']) : null,
            );
        } catch (\RuntimeException $e) {
            return $peticion->expectsJson()
                ? response()->json(['error' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        return $peticion->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('exito', 'Cita movida.');
    }

    // ------------------------------------------------------------------
    // Bloqueos
    // ------------------------------------------------------------------

    public function bloquear(Request $peticion)
    {
        $datos = $peticion->validate([
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
            'fecha'      => ['required', 'date'],
            'hora_ini'   => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'hora_fin'   => ['required', 'regex:/^\d{2}:\d{2}$/', 'after:hora_ini'],
            'motivo'     => ['nullable', 'string', 'max:160'],
        ]);

        BloqueoAgenda::create($datos + ['creado_por' => SesionSalon::usuario()?->id]);

        return back()->with('exito', 'Bloqueo añadido.');
    }

    public function borrarBloqueo(BloqueoAgenda $bloqueo)
    {
        $bloqueo->delete();

        return back()->with('exito', 'Bloqueo eliminado.');
    }

    // ------------------------------------------------------------------
    // Búsqueda de clientes (autocompletar)
    // ------------------------------------------------------------------

    public function buscarClientes(Request $peticion)
    {
        $texto = $peticion->string('q')->toString();

        if (strlen($texto) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Cliente::buscar($texto)->limit(10)->get()
                ->map(fn ($c) => [
                    'id'       => $c->id,
                    'nombre'   => $c->nombreCompleto(),
                    'telefono' => $c->telefono,
                    'avisos'   => $c->no_shows > 0 ? "{$c->no_shows} plantón(es)" : null,
                ])
        );
    }
}
