<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Festivo;
use App\Services\GestorFestivos;
use Illuminate\Http\Request;

class FestivoController extends Controller
{
    public function __construct(
        protected GestorFestivos $gestor = new GestorFestivos(),
    ) {
    }

    public function index(Request $peticion)
    {
        $ano = (int) $peticion->input('ano', now()->year);

        return view('panel.festivos.index', [
            'festivos'   => $this->gestor->delAno($ano),
            'ano'        => $ano,
            'proximos'   => Festivo::proximos(45)->orderBy('fecha')->get(),
            'laborables' => $this->gestor->festivosLaborables($ano),
        ]);
    }

    public function guardar(Request $peticion)
    {
        $datos = $peticion->validate([
            'fecha'         => ['required', 'date'],
            'nombre'        => ['required', 'string', 'max:120'],
            'ambito'        => ['required', 'in:NACIONAL,AUTONOMICO,LOCAL,CIERRE'],
            'media_jornada' => ['nullable', 'in:MANANA,TARDE'],
            'observaciones' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $this->gestor->crear(
                $datos['fecha'],
                $datos['nombre'],
                $datos['ambito'],
                $datos['media_jornada'] ?? null,
                $datos['observaciones'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Festivo guardado. La agenda ya no ofrece huecos ese dia.');
    }

    public function borrar(Festivo $festivo)
    {
        $this->gestor->borrar($festivo);

        return back()->with('exito', 'Festivo eliminado. Ese dia vuelve a estar disponible.');
    }

    /** Alta de los festivos nacionales y autonomicos de un ano. */
    public function importar(Request $peticion)
    {
        $ano = (int) $peticion->input('ano', now()->year);

        $resultado = $this->gestor->importarAno(
            $ano,
            $peticion->boolean('canarias', true),
        );

        $mensaje = $resultado['creados'] . ' festivo(s) anadido(s)';

        if ($resultado['existentes'] > 0) {
            $mensaje .= ', ' . $resultado['existentes'] . ' ya estaban';
        }

        return back()->with('exito', $mensaje
            . '. Revisa los locales de tu municipio: esos no los sabemos.');
    }
}
