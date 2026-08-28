<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Support\LimitesPlan;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index()
    {
        return view('panel.usuarios.index', [
            'usuarios' => Usuario::withTrashed()->with('perfil')
                            ->orderBy('estado')->orderBy('nombre')->get(),
            'limites'  => LimitesPlan::resumen(),
        ]);
    }

    public function crear()
    {
        return view('panel.usuarios.form', [
            'usuario' => new Usuario(),
            'perfiles'=> Perfil::orderBy('nombre')->get(),
            'limites' => LimitesPlan::resumen(),
        ]);
    }

    public function editar(Usuario $usuario)
    {
        return view('panel.usuarios.form', [
            'usuario' => $usuario,
            'perfiles'=> Perfil::orderBy('nombre')->get(),
            'limites' => LimitesPlan::resumen(),
        ]);
    }

    public function guardar(Request $peticion, ?Usuario $usuario = null)
    {
        $esNuevo = ! ($usuario && $usuario->exists);

        $datos = $peticion->validate([
            'nombre'         => ['required', 'string', 'max:80'],
            'alias'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:160',
                                 Rule::unique('usuarios')->ignore($usuario?->id)],
            'nif'            => ['nullable', 'string', 'max:20'],
            'telefono'       => ['nullable', 'string', 'max:30'],
            'perfil_id'      => ['required', 'exists:perfiles,id'],
            'color_agenda'   => ['nullable', 'string', 'max:9'],
            'comision_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'horas_semana'   => ['nullable', 'numeric', 'min:0', 'max:60'],
            'fecha_alta_lab' => ['nullable', 'date'],
            'orden'          => ['nullable', 'integer', 'min:0'],
        ]);

        $datos['es_profesional'] = $peticion->boolean('es_profesional');
        $datos['en_formacion']   = $peticion->boolean('en_formacion');
        $datos['ficha_jornada']  = $peticion->boolean('ficha_jornada');

        /**
         * El límite del plan solo se comprueba al DAR DE ALTA.
         *
         * Si un salón baja de plan teniendo cinco profesionales, no se le
         * desactivan dos por sorpresa: eso dejaría citas huérfanas.
         * Simplemente no puede añadir más.
         */
        if ($esNuevo && $datos['es_profesional']) {
            try {
                LimitesPlan::comprobarProfesional();
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        if ($esNuevo) {
            $pin = $peticion->input('pin') ?: (string) random_int(1000, 9999);
            $password = $peticion->input('password') ?: Str::random(12);

            $usuario = Usuario::create($datos + [
                'pin'      => $pin,
                'password' => $password,
                'estado'   => 'ACTIVO',
            ]);

            Auditoria::registrar('usuario_creado', 'usuarios', $usuario->id, [
                'nombre' => $usuario->nombre,
                'perfil' => $usuario->perfil?->nombre,
            ]);

            /**
             * El PIN y la contraseña se muestran UNA vez.
             * Se guardan hasheados, así que no hay forma de recuperarlos:
             * si se pierden, se generan otros.
             */
            return redirect()->route('panel.usuarios')->with('credenciales', [
                'nombre'   => $usuario->nombre,
                'pin'      => $pin,
                'password' => $password,
            ]);
        }

        $usuario->update($datos);

        Auditoria::registrar('usuario_editado', 'usuarios', $usuario->id,
            ['nombre' => $usuario->nombre]);

        return redirect()->route('panel.usuarios')->with('exito', 'Usuario actualizado.');
    }

    /**
     * Contrasena nueva: al azar o la que indique el encargado.
     *
     * Antes solo se podia generar aleatoria, y eso obliga a apuntar doce
     * caracteres raros cada vez. En un salon lo practico es poder poner
     * una que la persona recuerde.
     */
    public function nuevaPassword(Request $peticion, Usuario $usuario)
    {
        $peticion->validate([
            'password' => ['nullable', 'string', 'min:6', 'max:60'],
        ], [
            'password.min' => 'La contrasena necesita al menos seis caracteres.',
        ]);

        $password = $peticion->input('password') ?: Str::random(12);

        $usuario->forceFill(['password' => $password])->save();

        Auditoria::registrar('usuario_password_reiniciada', 'usuarios', $usuario->id, [
            'nombre'    => $usuario->nombre,

            // Se anota SI la eligio a mano, pero nunca cual: la auditoria
            // no puede convertirse en un almacen de contrasenas
            'a_medida'  => $peticion->filled('password'),
        ]);

        return back()->with('credenciales', [
            'nombre'   => $usuario->nombre,
            'password' => $password,
        ]);
    }

    /**
     * PIN nuevo: al azar o el que indique el encargado.
     *
     * Cuatro digitos exactos, que es lo que espera el teclado del TPV.
     */
    public function nuevoPin(Request $peticion, Usuario $usuario)
    {
        $peticion->validate([
            'pin' => ['nullable', 'digits:4'],
        ], [
            'pin.digits' => 'El PIN son cuatro digitos.',
        ]);

        $pin = $peticion->input('pin') ?: (string) random_int(1000, 9999);

        /**
         * Se comprueba que no lo tenga ya otra persona.
         *
         * Con PINs repetidos, el selector no sabria a quien dejar entrar,
         * y lo que teclee uno se le apuntaria al otro. En un salon donde
         * el reparto de produccion decide lo que cobra cada cual, eso es
         * un problema serio.
         */
        foreach (Usuario::activos()->where('id', '!=', $usuario->id)->get() as $otro) {
            if (\Illuminate\Support\Facades\Hash::check($pin, $otro->pin)) {
                return back()->with('error',
                    'Ese PIN ya lo tiene ' . $otro->nombre . '. Elige otro.');
            }
        }

        $usuario->forceFill(['pin' => $pin, 'intentos_pin' => 0])->save();

        Auditoria::registrar('usuario_pin_reiniciado', 'usuarios', $usuario->id, [
            'nombre'   => $usuario->nombre,
            'a_medida' => $peticion->filled('pin'),
        ]);

        return back()->with('credenciales', [
            'nombre' => $usuario->nombre,
            'pin'    => $pin,
        ]);
    }

    /**
     * Baja de un usuario.
     *
     * NO se borra: se desactiva. Sus tickets, sus fichajes y sus citas
     * tienen que seguir existiendo, y el registro de jornada hay que
     * conservarlo cuatro años aunque la persona se haya ido.
     */
    public function desactivar(Request $peticion, Usuario $usuario)
    {
        if ($usuario->id === SesionSalon::usuario()->id) {
            return back()->with('error', 'No puedes darte de baja a ti mismo.');
        }

        if ($usuario->perfil?->clave === 'propietario'
            && Usuario::activos()->whereHas('perfil', fn ($q) => $q->where('clave', 'propietario'))->count() <= 1) {
            return back()->with('error',
                'Es el único propietario. Asigna ese perfil a otra persona antes de darlo de baja.');
        }

        $usuario->update(['estado' => 'INACTIVO']);
        $usuario->delete();   // borrado lógico

        Auditoria::registrar('usuario_baja', 'usuarios', $usuario->id,
            ['nombre' => $usuario->nombre]);

        return back()->with('exito',
            $usuario->nombre . ' ya no puede entrar. Su historial y sus fichajes se conservan.');
    }

    public function reactivar(Usuario $usuario)
    {
        if ($usuario->es_profesional) {
            try {
                LimitesPlan::comprobarProfesional();
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        $usuario->restore();
        $usuario->update(['estado' => 'ACTIVO', 'intentos_pin' => 0]);

        Auditoria::registrar('usuario_reactivado', 'usuarios', $usuario->id,
            ['nombre' => $usuario->nombre]);

        return back()->with('exito', $usuario->nombre . ' vuelve a tener acceso.');
    }

    /** Desbloquea a quien falló el PIN demasiadas veces. */
    public function desbloquear(Usuario $usuario)
    {
        $usuario->forceFill(['intentos_pin' => 0, 'bloqueado_hasta' => null])->save();

        return back()->with('exito', $usuario->nombre . ' desbloqueado.');
    }
}
