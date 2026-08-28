<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\ConfigEmpresa;
use Illuminate\Http\Request;

class AjustesController extends Controller
{
    /**
     * Indice de ajustes.
     *
     * Antes esta ruta servia directamente la pantalla de reservas, asi
     * que «Ajustes» en el menu llevaba a una seccion concreta y el resto
     * quedaba escondido: solo se llegaba escribiendo la direccion. De ahi
     * que ni el propio desarrollador encontrara la gestion de usuarios.
     */
    public function index()
    {
        return view('panel.ajustes.index', [
            'empresa' => tenant(),
        ]);
    }

    /** La pantalla de reservas, que antes ocupaba el indice. */
    public function reservas()
    {
        return view('panel.ajustes.reservas', [
            'ajustes' => ConfigEmpresa::pluck('valor', 'clave')->all(),
            'empresa' => tenant(),
        ]);
    }

    public function guardar(Request $peticion)
    {
        $datos = $peticion->validate([
            'antelacion_min_horas'      => ['required', 'integer', 'min:0', 'max:720'],
            'antelacion_max_dias'       => ['required', 'integer', 'min:1', 'max:365'],
            'cancelacion_horas_min'     => ['required', 'integer', 'min:0', 'max:720'],
            'caducidad_pendiente_horas' => ['required', 'integer', 'min:1', 'max:720'],
            'no_shows_para_exigir_pago' => ['required', 'integer', 'min:1', 'max:20'],
            'agenda_hora_ini'           => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'agenda_hora_fin'           => ['required', 'regex:/^\d{2}:\d{2}$/', 'after:agenda_hora_ini'],
        ], [
            'agenda_hora_fin.after' => 'La agenda no puede terminar antes de empezar.',
        ]);

        $datos['confirmacion_automatica'] = $peticion->boolean('confirmacion_automatica') ? 'true' : 'false';

        foreach ($datos as $clave => $valor) {
            ConfigEmpresa::updateOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
        }

        Auditoria::registrar('ajustes_guardados', 'config', null, array_keys($datos));

        return back()->with('exito', 'Ajustes guardados.');
    }
}
