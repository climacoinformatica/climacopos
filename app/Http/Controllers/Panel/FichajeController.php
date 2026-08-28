<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Fichaje;
use App\Models\Usuario;
use App\Services\GestorFichajes;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FichajeController extends Controller
{
    public function __construct(
        protected GestorFichajes $gestor = new GestorFichajes(),
    ) {
    }

    /** Mi jornada: lo que cada persona ve de sí misma. */
    public function index()
    {
        $usuario = SesionSalon::usuario();

        return view('panel.fichajes.index', [
            'usuario' => $usuario,
            'estado'  => $this->gestor->estado($usuario),
            'jornada' => $this->gestor->jornada($usuario, now()),
            'semana'  => $this->semana($usuario),

            // Quién está dentro solo lo ve quien gestiona personal
            'dentro'  => $usuario->tienePermiso(Permisos::USUARIOS_GESTIONAR)
                         ? $this->gestor->quienEstaDentro() : collect(),
        ]);
    }

    protected function semana(Usuario $usuario): array
    {
        $dias = [];
        $fecha = now()->startOfWeek();
        $total = 0;

        while ($fecha->lte(now()->endOfWeek())) {
            $jornada = $this->gestor->jornada($usuario, $fecha);

            $dias[] = [
                'fecha'      => $fecha->copy(),
                'minutos'    => $jornada['minutos'],
                'incompleta' => $jornada['incompleta'],
                'hoy'        => $fecha->isToday(),
                'futuro'     => $fecha->isFuture(),
            ];

            $total += $jornada['minutos'];
            $fecha->addDay();
        }

        return ['dias' => $dias, 'total' => $total];
    }

    public function fichar(Request $peticion)
    {
        $usuario = SesionSalon::usuario();

        try {
            $fichaje = $peticion->filled('tipo')
                ? $this->gestor->fichar($usuario, $peticion->input('tipo'), 'PANEL')
                : $this->gestor->ficharSiguiente($usuario);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito',
            $fichaje->etiqueta() . ' registrada a las ' . $fichaje->hora() . '.');
    }

    // ------------------------------------------------------------------
    // Informe
    // ------------------------------------------------------------------

    public function informe(Request $peticion)
    {
        $ano = (int) $peticion->input('ano', now()->year);
        $mes = (int) $peticion->input('mes', now()->month);

        $usuarios = Usuario::activos()->where('ficha_jornada', true)->orderBy('nombre')->get();

        $usuarioId = $peticion->integer('usuario_id') ?: $usuarios->first()?->id;
        $usuario = $usuarioId ? Usuario::find($usuarioId) : null;

        return view('panel.fichajes.informe', [
            'usuarios' => $usuarios,
            'usuario'  => $usuario,
            'resumen'  => $usuario ? $this->gestor->mes($usuario, $ano, $mes) : null,
            'ano'      => $ano,
            'mes'      => $mes,
        ]);
    }

    /**
     * Exportación del registro de jornada.
     *
     * La empresa está obligada a entregar a cada persona trabajadora el
     * resumen de su jornada y a tenerlo disponible para la Inspección.
     * BOM y punto y coma para que Excel en español lo abra bien.
     */
    public function exportar(Request $peticion)
    {
        $usuario = Usuario::findOrFail($peticion->integer('usuario_id'));

        return $this->generarCsv(
            $usuario,
            (int) $peticion->input('ano', now()->year),
            (int) $peticion->input('mes', now()->month),
        );
    }

    /**
     * MI registro de jornada.
     *
     * La normativa reconoce a cada persona trabajadora el derecho de
     * acceder a su propio registro. Hacerlo depender de que un
     * responsable se lo exporte no cumple ese derecho: tiene que poder
     * descargarlo por si misma, sin pedir permiso a nadie.
     */
    public function miRegistro(Request $peticion)
    {
        $usuario = SesionSalon::usuario();

        $ano = (int) $peticion->input('ano', now()->year);
        $mes = (int) $peticion->input('mes', now()->month);

        return view('panel.fichajes.mi-registro', [
            'usuario' => $usuario,
            'resumen' => $this->gestor->mes($usuario, $ano, $mes),
            'ano'     => $ano,
            'mes'     => $mes,
        ]);
    }

    public function miExportacion(Request $peticion)
    {
        return $this->generarCsv(
            SesionSalon::usuario(),
            (int) $peticion->input('ano', now()->year),
            (int) $peticion->input('mes', now()->month),
        );
    }

    protected function generarCsv(Usuario $usuario, int $ano, int $mes)
    {
        $resumen = $this->gestor->mes($usuario, $ano, $mes);
        $empresa = tenant();

        $nombre = 'jornada_' . Str::slug($usuario->nombre)
                . '_' . sprintf('%04d%02d', $ano, $mes) . '.csv';

        return new StreamedResponse(function () use ($resumen, $usuario, $empresa) {
            $salida = fopen('php://output', 'w');

            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, ['REGISTRO DE JORNADA'], ';');
            fputcsv($salida, ['Empresa', $empresa->razon_social ?: $empresa->nombre_comercial], ';');
            fputcsv($salida, ['NIF empresa', $empresa->nif ?? ''], ';');
            fputcsv($salida, ['Trabajador', $usuario->nombre], ';');
            fputcsv($salida, ['NIF trabajador', $usuario->nif ?? ''], ';');
            fputcsv($salida, ['Periodo',
                $resumen['desde']->format('d/m/Y') . ' a ' . $resumen['hasta']->format('d/m/Y')], ';');
            fputcsv($salida, ['Generado', now()->format('d/m/Y H:i')], ';');
            fputcsv($salida, [], ';');

            fputcsv($salida, ['Fecha', 'Entrada', 'Salida', 'Pausa (min)', 'Horas', 'Incidencia'], ';');

            foreach ($resumen['dias'] as $dia) {
                /**
                 * Los dias de ausencia SI salen en el registro.
                 *
                 * Un mes con huecos sin explicar levanta preguntas; con
                 * «Vacaciones» escrito al lado, no.
                 */
                if (! $dia['trabajado']) {
                    if (! empty($dia['ausencia'])) {
                        fputcsv($salida, [
                            $dia['fecha']->format('d/m/Y'),
                            '', '', '', '',
                            $dia['ausencia']->etiqueta(),
                        ], ';');
                    }

                    continue;
                }

                $entrada = $dia['fichajes']->firstWhere('tipo', 'ENTRADA');
                $salidas = $dia['fichajes']->where('tipo', 'SALIDA');

                fputcsv($salida, [
                    $dia['fecha']->format('d/m/Y'),
                    $entrada?->hora() ?? '',
                    $salidas->last()?->hora() ?? '',
                    $dia['pausa'] ?: '',
                    number_format($dia['minutos'] / 60, 2, ',', ''),
                    $dia['incompleta'] ? 'Registro incompleto' : '',
                ], ';');
            }

            fputcsv($salida, [], ';');
            fputcsv($salida, ['TOTAL',
                '', '', '',
                number_format($resumen['total_minutos'] / 60, 2, ',', ''),
                $resumen['dias_incompletos'] > 0
                    ? $resumen['dias_incompletos'] . ' día(s) con incidencias' : '',
            ], ';');

            fclose($salida);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    // ------------------------------------------------------------------
    // Correcciones
    // ------------------------------------------------------------------

    public function corregir(Request $peticion, Fichaje $fichaje)
    {
        $datos = $peticion->validate([
            'fecha_hora' => ['required', 'date'],
            'motivo'     => ['required', 'string', 'max:300'],
        ], [
            'motivo.required' => 'Hay que indicar el motivo de la corrección.',
        ]);

        try {
            $this->gestor->corregir(
                $fichaje,
                Carbon::parse($datos['fecha_hora']),
                $datos['motivo'],
                SesionSalon::usuario(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito',
            'Fichaje corregido. El original queda registrado, como exige la normativa.');
    }

    public function anadir(Request $peticion)
    {
        $datos = $peticion->validate([
            'usuario_id' => ['required', 'exists:usuarios,id'],
            'tipo'       => ['required', 'in:ENTRADA,SALIDA,PAUSA_INICIO,PAUSA_FIN'],
            'fecha_hora' => ['required', 'date'],
            'motivo'     => ['required', 'string', 'max:300'],
        ]);

        try {
            $this->gestor->anadirManual(
                Usuario::findOrFail($datos['usuario_id']),
                $datos['tipo'],
                Carbon::parse($datos['fecha_hora']),
                $datos['motivo'],
                SesionSalon::usuario(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Fichaje añadido. Queda marcado como manual.');
    }
}
