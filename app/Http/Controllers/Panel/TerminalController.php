<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Terminal;
use App\Models\Usuario;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Paso 1 de la opcion C: vincular el equipo con credenciales completas.
 * Se hace una sola vez por dispositivo; luego basta el PIN.
 */
class TerminalController extends Controller
{
    public function mostrarVinculacion()
    {
        if (SesionSalon::terminalVinculado()) {
            return redirect()->route('panel.selector');
        }

        return view('panel.vincular', [
            'empresa'    => tenant(),
            'terminales' => Terminal::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function vincular(Request $request)
    {
        $datos = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'terminal_id' => ['nullable', 'integer'],
            'nombre_nuevo'=> ['nullable', 'string', 'max:60'],
        ]);

        $usuario = Usuario::activos()->where('email', $datos['email'])->first();

        if (! $usuario || ! $usuario->comprobarPassword($datos['password'])) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas.',
            ]);
        }

        if (! $usuario->tienePermiso(Permisos::TERMINALES_VINCULAR)) {
            throw ValidationException::withMessages([
                'email' => 'Este usuario no puede vincular equipos.',
            ]);
        }

        $terminal = $datos['terminal_id']
            ? Terminal::where('activo', true)->findOrFail($datos['terminal_id'])
            : Terminal::create([
                'nombre' => $datos['nombre_nuevo'] ?: 'Terminal ' . (Terminal::count() + 1),
                'codigo' => 'T' . str_pad((string) (Terminal::max('id') + 1), 3, '0', STR_PAD_LEFT),
            ]);

        SesionSalon::vincularTerminal($terminal, $usuario);

        return redirect()->route('panel.selector')
            ->with('exito', "Equipo vinculado como «{$terminal->nombre}».");
    }
}
