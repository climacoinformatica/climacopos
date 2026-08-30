<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use App\Services\GestorFichajes;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SelectorController extends Controller
{
    /** Rejilla de usuarios: la primera pantalla del programa. */
    public function mostrar(Request $peticion)
    {
        /**
         * Volver al TPV tras meter el PIN.
         *
         * Con el ajuste `tras_cobrar` en SELECTOR, cada cobro devuelve
         * a esta pantalla para que el siguiente se identifique. Sin
         * esto, quien entra aparece en el Inicio y tiene que navegar
         * otra vez al TPV en cada venta: en un mostrador con cola, eso
         * son dos toques de mas todo el dia.
         *
         * Se aprovecha `url.intended`, que es lo que ya lee
         * redirect()->intended() en entrar(). Sobrevive al
         * session()->regenerate() del login porque regenerate migra los
         * datos de la sesion.
         */
        if ($peticion->query('destino') === 'tpv') {
            session(['url.intended' => route('panel.tpv')]);
        }

        $usuarios = Usuario::activos()
            ->with('perfil')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        /**
         * Estado de fichaje, solo de quien ficha.
         *
         * Se calcula aqui y no en la vista para no lanzar una consulta
         * por tarjeta desde el Blade. Quien no tiene ficha_jornada no
         * aparece en el array y su tarjeta sale como siempre.
         */
        $fichajes = new GestorFichajes();
        $estados  = [];

        foreach ($usuarios as $u) {
            if ($u->ficha_jornada) {
                $estados[$u->id] = $fichajes->estado($u);
            }
        }

        return view('panel.selector', [
            'usuarios' => $usuarios,
            'estados'  => $estados,
            'terminal' => SesionSalon::terminal(),
            'empresa'  => tenant(),
        ]);
    }

    /** Entrada con PIN. */
    public function entrar(Request $request)
    {
        $datos = $request->validate([
            'usuario_id' => ['required', 'integer'],
            'pin'        => ['required', 'string', 'min:4', 'max:8'],
        ]);

        $usuario = Usuario::activos()->find($datos['usuario_id']);

        if (! $usuario) {
            throw ValidationException::withMessages([
                'usuario_id' => 'Ese usuario no esta disponible.',
            ]);
        }

        if ($usuario->pinBloqueado()) {
            $minutos = (int) ceil(now()->diffInMinutes($usuario->pin_bloqueado_hasta, false));

            throw ValidationException::withMessages([
                'pin' => "Demasiados intentos. Vuelve a probar en {$minutos} minuto(s).",
            ]);
        }

        if (! $usuario->comprobarPin($datos['pin'])) {
            Auditoria::registrar('login_fallido', 'usuarios', $usuario->id,
                ['intentos' => $usuario->intentos_pin], $usuario->id);

            throw ValidationException::withMessages([
                'pin' => 'PIN incorrecto.',
            ]);
        }

        SesionSalon::entrar($usuario);

        /**
         * Antes de seguir, la pantalla de fichaje.
         *
         * Solo si el usuario ficha jornada Y esta fuera. Si ya entro
         * antes (una vuelta del selector tras cobrar, por ejemplo) no
         * se le pregunta nada: seria un estorbo en cada venta.
         *
         * El destino se respeta: la pantalla de fichaje continua a
         * donde iba, TPV o Inicio.
         */
        if ($usuario->ficha_jornada && (new GestorFichajes())->estado($usuario) === 'FUERA') {
            return redirect()->route('panel.selector.entrada');
        }

        return redirect()->intended($this->destinoInicial());
    }

    /**
     * A donde va el usuario nada mas entrar.
     *
     * Al TPV: es donde se trabaja, y mandar a todo el mundo al Inicio
     * obligaba a un toque de mas en cada entrada, varias veces al dia.
     *
     * Se comprueba el permiso antes: quien no puede vender recibiria un
     * 403 nada mas identificarse y se quedaria sin poder usar nada.
     */
    protected function destinoInicial(): string
    {
        $usuario = SesionSalon::usuario();

        return $usuario?->tienePermiso(Permisos::TPV_VENDER)
            ? route('panel.tpv')
            : route('panel.inicio');
    }

    /** Pregunta de entrada, tras identificarse. */
    public function preguntarEntrada()
    {
        $usuario = SesionSalon::usuario();

        // Si ya no procede, se sigue el camino normal sin molestar
        if (! $usuario || ! $usuario->ficha_jornada
            || (new GestorFichajes())->estado($usuario) !== 'FUERA') {
            return redirect()->intended($this->destinoInicial());
        }

        return view('panel.fichaje-entrada', ['usuario' => $usuario]);
    }

    /** Registra la entrada y continua. */
    public function registrarEntrada(Request $peticion)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario) {
            return redirect()->route('panel.selector');
        }

        if ($peticion->boolean('fichar')) {
            try {
                (new GestorFichajes())->fichar($usuario, 'ENTRADA', 'TERMINAL');
            } catch (\Throwable $e) {
                /**
                 * Un fallo de fichaje NO puede dejar a nadie sin entrar
                 * a trabajar. Se avisa y se sigue: el fichaje se
                 * corrige despues desde la pantalla de Fichar.
                 */
                return redirect()->intended($this->destinoInicial())
                    ->with('error', 'No se pudo fichar la entrada: ' . $e->getMessage());
            }

            return redirect()->intended($this->destinoInicial())
                ->with('exito', 'Entrada fichada a las ' . now()->format('H:i') . '.');
        }

        return redirect()->intended($this->destinoInicial());
    }

    /**
     * Marcar la salida desde el propio selector.
     *
     * Pide el PIN igual que entrar: el fichaje es un registro laboral y
     * cualquiera que pase por delante del mostrador no puede cerrarle
     * la jornada a otro.
     *
     * NO inicia sesion: se ficha y se vuelve al selector.
     */
    public function marcarSalida(Request $peticion)
    {
        $datos = $peticion->validate([
            'usuario_id' => ['required', 'integer'],
            'pin'        => ['required', 'string', 'min:4', 'max:8'],
        ]);

        $usuario = Usuario::activos()->find($datos['usuario_id']);

        if (! $usuario || ! $usuario->ficha_jornada) {
            throw ValidationException::withMessages([
                'usuario_id' => 'Ese usuario no esta disponible.',
            ]);
        }

        if ($usuario->pinBloqueado()) {
            $minutos = (int) ceil(now()->diffInMinutes($usuario->pin_bloqueado_hasta, false));

            throw ValidationException::withMessages([
                'pin' => "Demasiados intentos. Vuelve a probar en {$minutos} minuto(s).",
            ]);
        }

        if (! $usuario->comprobarPin($datos['pin'])) {
            Auditoria::registrar('login_fallido', 'usuarios', $usuario->id,
                ['intentos' => $usuario->intentos_pin, 'accion' => 'salida'], $usuario->id);

            throw ValidationException::withMessages([
                'pin' => 'PIN incorrecto.',
            ]);
        }

        try {
            (new GestorFichajes())->fichar($usuario, 'SALIDA', 'TERMINAL');
        } catch (\Throwable $e) {
            return redirect()->route('panel.selector')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('panel.selector')
            ->with('exito', $usuario->nombre . ', salida fichada a las ' . now()->format('H:i') . '.');
    }

    public function salir()
    {
        SesionSalon::cerrar();

        return redirect()->route('panel.selector');
    }
}
