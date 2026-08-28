<?php

namespace App\Http\Middleware;

use App\Services\GestorSuscripciones;
use Closure;
use Illuminate\Http\Request;

/**
 * Aplica el estado de la suscripción dentro del panel del salón.
 *
 *   ACTIVA / PRUEBA   todo funciona
 *   MOROSA            todo funciona, con aviso visible
 *   SUSPENDIDA        solo lectura, sin informes
 *
 * Se comprueba en cada petición y no solo al entrar, para que un salón
 * suspendido a media sesión no siga vendiendo hasta que cierre el
 * navegador.
 */
class ComprobarSuscripcion
{
    /** Rutas que siguen accesibles aunque esté suspendido. */
    protected const SIEMPRE_PERMITIDAS = [
        'panel.selector',
        'panel.selector.entrar',
        'panel.salir',
        'panel.reautenticar',
        'panel.reautenticar.post',
        'panel.terminal.vincular',
        'panel.terminal.vincular.post',
        'panel.suscripcion',
        'panel.suscripcion.contratar',
        'panel.suscripcion.portal',
        'panel.inicio',
    ];

    public function handle(Request $peticion, Closure $siguiente)
    {
        $empresa = tenant();

        if (! $empresa) {
            return $siguiente($peticion);
        }

        $soloLectura = GestorSuscripciones::enSoloLectura($empresa);

        // Disponible en todas las vistas para pintar los avisos
        view()->share('soloLectura', $soloLectura);
        view()->share('estadoSuscripcion', $empresa->estado);

        if (! $soloLectura) {
            return $siguiente($peticion);
        }

        $ruta = $peticion->route()?->getName() ?? '';

        if (in_array($ruta, self::SIEMPRE_PERMITIDAS, true)) {
            return $siguiente($peticion);
        }

        /**
         * Los informes se cortan aunque sean de solo lectura.
         *
         * Es la única función que un salón suspendido podría seguir
         * aprovechando de verdad: exportar toda su información y
         * marcharse sin pagar. Consultar la agenda para atender a quien
         * ya tenía cita, en cambio, no perjudica a nadie.
         */
        if (str_starts_with($ruta, 'panel.informes')) {
            return $this->cortar($peticion,
                'Los informes no están disponibles mientras la suscripción esté suspendida.');
        }

        // Escritura bloqueada
        if (! $peticion->isMethodSafe()) {
            return $this->cortar($peticion,
                'Tu cuenta está en modo solo lectura. Regulariza el pago para volver a operar.');
        }

        return $siguiente($peticion);
    }

    protected function cortar(Request $peticion, string $mensaje)
    {
        if ($peticion->expectsJson()) {
            return response()->json(['error' => 'suspendida', 'mensaje' => $mensaje], 402);
        }

        return redirect()->route('panel.suscripcion')->with('error', $mensaje);
    }
}
