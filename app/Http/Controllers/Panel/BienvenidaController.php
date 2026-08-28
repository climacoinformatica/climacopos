<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\Familia;
use App\Models\Usuario;
use App\Models\UsuarioHorario;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

/**
 * Asistente de primera configuracion.
 *
 * Cuatro pasos con lo minimo para que el salon funcione: datos fiscales,
 * horario, servicios y equipo. Todo lo demas se puede dejar para despues.
 *
 * Pedir mas aqui seria contraproducente: quien acaba de crear la cuenta
 * quiere ver el programa funcionando, no rellenar formularios.
 */
class BienvenidaController extends Controller
{
    public function mostrar()
    {
        $empresa = tenant();

        return view('panel.bienvenida.asistente', [
            'empresa'   => $empresa,
            'paso'      => (int) ($empresa->paso_configuracion ?? 1),
            'familias'  => Familia::withCount('articulos')->get(),
            'usuarios'  => Usuario::activos()->get(),
        ]);
    }

    /** Paso 1: datos fiscales. */
    public function fiscales(Request $peticion)
    {
        $datos = $peticion->validate([
            'razon_social' => ['required', 'string', 'max:150'],
            'nif'          => ['required', 'string', 'max:20'],
            'direccion'    => ['required', 'string', 'max:200'],
            'poblacion'    => ['required', 'string', 'max:100'],
            'provincia'    => ['required', 'string', 'max:60'],
            'codigo_postal'=> ['required', 'string', 'max:10'],
            'telefono'     => ['nullable', 'string', 'max:30'],
        ]);

        tenant()->update($datos + ['paso_configuracion' => 2]);

        return redirect()->route('panel.bienvenida')
            ->with('exito', 'Datos guardados.');
    }

    /** Paso 2: horario de apertura. */
    public function horario(Request $peticion)
    {
        $peticion->validate([
            'dias'     => ['required', 'array', 'min:1'],
            'hora_ini' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_ini'],
        ], [
            'dias.required' => 'Marca al menos un día de apertura.',
            'hora_fin.after'=> 'La hora de cierre tiene que ser posterior a la de apertura.',
        ]);

        /**
         * El horario se aplica al propietario, que de momento es el unico
         * usuario. Cuando anada mas profesionales, cada uno tendra el suyo:
         * este solo evita que la agenda salga vacia el primer dia.
         */
        $usuario = SesionSalon::usuario();

        UsuarioHorario::where('usuario_id', $usuario->id)->delete();

        foreach ($peticion->input('dias') as $dia) {
            UsuarioHorario::create([
                'usuario_id' => $usuario->id,
                'dia_semana' => (int) $dia,
                'hora_ini'   => $peticion->input('hora_ini'),
                'hora_fin'   => $peticion->input('hora_fin'),
            ]);
        }

        tenant()->update(['paso_configuracion' => 3]);

        return redirect()->route('panel.bienvenida')
            ->with('exito', 'Horario guardado.');
    }

    /** Paso 3: servicios de partida. */
    public function servicios(Request $peticion)
    {
        $peticion->validate([
            'servicios'             => ['required', 'array', 'min:1'],
            'servicios.*.nombre'    => ['required', 'string', 'max:120'],
            'servicios.*.precio'    => ['required', 'numeric', 'min:0'],
            'servicios.*.duracion'  => ['required', 'integer', 'min:5', 'max:600'],
        ], [
            'servicios.required' => 'Añade al menos un servicio.',
        ]);

        $familia = Familia::firstOrCreate(
            ['nombre' => 'Servicios'],
            ['tipo' => 'SERVICIO', 'color' => '#8b5cf6', 'orden' => 1],
        );

        foreach ($peticion->input('servicios') as $servicio) {
            if (blank($servicio['nombre'] ?? null)) {
                continue;
            }

            Articulo::create([
                'familia_id'   => $familia->id,
                'tipo'         => 'SERVICIO',
                'nombre'       => $servicio['nombre'],
                'precio'       => (float) $servicio['precio'],
                'impuesto_pct' => (float) ($servicio['impuesto'] ?? 7),
                'duracion_min' => (int) $servicio['duracion'],
                'activo'       => true,
            ]);
        }

        tenant()->update(['paso_configuracion' => 4]);

        return redirect()->route('panel.bienvenida')
            ->with('exito', 'Servicios creados.');
    }

    /** Paso 4: terminar. */
    public function terminar()
    {
        tenant()->update([
            'configurada_en'     => now(),
            'paso_configuracion' => 4,
        ]);

        return redirect()->route('panel.inicio')
            ->with('exito', '¡Listo! Tu salón ya está en marcha.');
    }

    /** Saltar el asistente y configurarlo despues. */
    public function saltar()
    {
        tenant()->update(['configurada_en' => now()]);

        return redirect()->route('panel.inicio')->with('exito',
            'Puedes terminar la configuración cuando quieras desde Ajustes.');
    }
}
