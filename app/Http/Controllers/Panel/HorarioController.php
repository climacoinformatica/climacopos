<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\UsuarioExcepcion;
use App\Models\UsuarioHorario;
use App\Support\Intervalo;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    /** Tramos que admite cada dia: manana y tarde. */
    public const TRAMOS_POR_DIA = 2;

    public function index()
    {
        return view('panel.agenda.horarios', [
            'profesionales' => Usuario::activos()->profesionales()
                ->with(['horarios' => fn ($q) => $q->orderBy('dia_semana')->orderBy('hora_ini')])
                ->orderBy('nombre')->get(),
            'excepciones'   => UsuarioExcepcion::with('usuario')
                ->where('fecha_fin', '>=', now()->subMonth()->toDateString())
                ->orderBy('fecha_ini')->get(),
        ]);
    }

    /**
     * Guarda el horario semanal, admitiendo jornada partida.
     *
     * El formulario envia siempre los 7 dias x 2 tramos. Un tramo con las
     * horas en blanco significa que no se trabaja en esa franja, asi que
     * las horas son opcionales y los vacios se descartan aqui.
     *
     * Formato recibido:  tramos[dia_semana][n][hora_ini|hora_fin]
     */
    public function guardarHorario(Request $peticion, Usuario $usuario)
    {
        $datos = $peticion->validate([
            'tramos'            => ['nullable', 'array'],
            'tramos.*'          => ['array'],
            'tramos.*.*.hora_ini' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'tramos.*.*.hora_fin' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
        ], [
            'tramos.*.*.hora_ini.regex' => 'Alguna hora de inicio no tiene formato valido.',
            'tramos.*.*.hora_fin.regex' => 'Alguna hora de fin no tiene formato valido.',
        ]);

        $dias = [
            0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miercoles',
            4 => 'jueves', 5 => 'viernes', 6 => 'sabado',
        ];

        $aInsertar = [];
        $avisos    = [];

        foreach ($datos['tramos'] ?? [] as $dia => $franjas) {
            $dia = (int) $dia;

            if (! isset($dias[$dia])) {
                continue;
            }

            $delDia = [];

            foreach ($franjas as $franja) {
                $ini = $franja['hora_ini'] ?? null;
                $fin = $franja['hora_fin'] ?? null;

                // Franja sin usar
                if (blank($ini) && blank($fin)) {
                    continue;
                }

                if (blank($ini) || blank($fin)) {
                    $avisos[] = "El {$dias[$dia]} tiene una franja a medio rellenar; se ha ignorado.";
                    continue;
                }

                if ($fin <= $ini) {
                    $avisos[] = "El {$dias[$dia]}, la franja {$ini}-{$fin} acaba antes de empezar; se ha ignorado.";
                    continue;
                }

                $nuevo = Intervalo::desdeHoras($ini, $fin);

                // Dos franjas del mismo dia no pueden pisarse
                $choca = false;

                foreach ($delDia as $existente) {
                    if ($nuevo->solapaCon($existente['intervalo'])) {
                        $avisos[] = "El {$dias[$dia]}, la franja {$ini}-{$fin} se solapa con "
                                  . $existente['intervalo'] . '; se ha ignorado.';
                        $choca = true;
                        break;
                    }
                }

                if ($choca) {
                    continue;
                }

                $delDia[] = ['intervalo' => $nuevo, 'ini' => $ini, 'fin' => $fin];
            }

            foreach ($delDia as $franja) {
                $aInsertar[] = [
                    'usuario_id' => $usuario->id,
                    'dia_semana' => $dia,
                    'hora_ini'   => $franja['ini'],
                    'hora_fin'   => $franja['fin'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Se reescribe el horario entero: son pocos registros y evita
        // arrastrar tramos huerfanos de ediciones anteriores.
        $usuario->horarios()->delete();

        if ($aInsertar !== []) {
            UsuarioHorario::insert($aInsertar);
        }

        $diasConTrabajo = count(array_unique(array_column($aInsertar, 'dia_semana')));

        $mensaje = $aInsertar === []
            ? "{$usuario->nombre} se ha quedado sin horario: no aparecera con huecos libres."
            : "Horario de {$usuario->nombre} guardado: {$diasConTrabajo} dia(s), "
              . count($aInsertar) . ' franja(s).';

        return back()
            ->with($aInsertar === [] ? 'error' : 'exito', $mensaje)
            ->with('avisos', $avisos);
    }

    public function guardarExcepcion(Request $peticion)
    {
        $datos = $peticion->validate([
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
            'fecha_ini'  => ['required', 'date'],
            'fecha_fin'  => ['required', 'date', 'after_or_equal:fecha_ini'],
            'tipo'       => ['required', 'in:VACACIONES,BAJA,FESTIVO,CIERRE,HORARIO_ESPECIAL'],
            'hora_ini'   => ['nullable', 'regex:/^\d{2}:\d{2}$/', 'required_if:tipo,HORARIO_ESPECIAL'],
            'hora_fin'   => ['nullable', 'regex:/^\d{2}:\d{2}$/', 'required_if:tipo,HORARIO_ESPECIAL'],
            'motivo'     => ['nullable', 'string', 'max:160'],
        ], [
            'fecha_fin.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'hora_ini.required_if'     => 'Un horario especial necesita hora de inicio.',
            'hora_fin.required_if'     => 'Un horario especial necesita hora de fin.',
        ]);

        if ($datos['tipo'] !== 'HORARIO_ESPECIAL') {
            $datos['hora_ini'] = null;
            $datos['hora_fin'] = null;
        }

        UsuarioExcepcion::create($datos);

        return back()->with('exito', 'Excepcion registrada.');
    }

    public function borrarExcepcion(UsuarioExcepcion $excepcion)
    {
        $excepcion->delete();

        return back()->with('exito', 'Excepcion eliminada.');
    }
}
