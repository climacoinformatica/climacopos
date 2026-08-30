<?php

namespace App\Http\Middleware;

use App\Support\LimitesPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el panel cuando se agotan las facturas del plan.
 *
 * NUNCA IMPIDE COBRAR
 *
 * El TPV y todo lo que tiene que ver con atender a una clienta quedan
 * fuera de este bloqueo. Dejar a un salon sin poder cobrar a alguien que
 * ya esta sentada en la silla, y sin ticket que darle, seria crearle un
 * problema fiscal para forzarle a pagar. Eso no se hace.
 *
 * Lo que se bloquea es lo demas: agenda, informes, catalogo, clientes.
 * El programa deja de ser comodo y el salon tiene un motivo real para
 * ampliar, pero su negocio sigue funcionando.
 */
class ComprobarFacturas
{
    /** Lo que sigue funcionando con el limite agotado. */
    protected array $permitidas = [
        // Cobrar, siempre
        'panel.tpv',
        'panel.tpv.*',

        // Y lo minimo para moverse
        'panel.inicio',
        'panel.suscripcion',
        'panel.suscripcion.*',
        'panel.salir',
        'panel.selector',
        'panel.selector.*',
        'panel.reautenticar',
        'panel.reautenticar.*',

        // La caja: hay que poder cerrar el dia
        'panel.caja',
        'panel.caja.*',

        // Fichar es un derecho, no una funcion del plan
        'panel.fichajes',
        'panel.fichajes.*',
    ];

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if (! LimitesPlan::facturasAgotadas()) {
            return $siguiente($peticion);
        }

        foreach ($this->permitidas as $patron) {
            if ($peticion->routeIs($patron)) {
                return $siguiente($peticion);
            }
        }

        if ($peticion->expectsJson()) {
            return response()->json([
                'ok'    => false,
                'error' => 'Has llegado al limite de facturas de tu plan.',
            ], 402);
        }

        return redirect()->route('panel.suscripcion')
            ->with('error',
                'Has llegado al limite de facturas de tu plan este mes. '
                . 'Puedes seguir cobrando con normalidad, pero el resto del '
                . 'programa estara limitado hasta que amplies el plan o '
                . 'empiece el mes que viene.');
    }
}
