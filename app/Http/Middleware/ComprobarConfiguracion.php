<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lleva al asistente hasta que el salon este configurado.
 *
 * Un salon sin datos fiscales no puede emitir una factura, y sin horario
 * la agenda sale vacia. Dejar entrar al TPV en ese estado solo produce
 * errores que el cliente no sabe interpretar y una llamada de soporte.
 */
class ComprobarConfiguracion
{
    /** Rutas que siguen siendo accesibles durante la configuracion. */
    protected array $permitidas = [
        'panel.bienvenida',
        'panel.bienvenida.*',
        'panel.salir',
        'panel.selector',
        'panel.selector.*',
        'panel.reautenticar',
        'panel.reautenticar.*',
    ];

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $empresa = tenant();

        if (! $empresa || $empresa->configurada_en) {
            return $siguiente($peticion);
        }

        foreach ($this->permitidas as $patron) {
            if ($peticion->routeIs($patron)) {
                return $siguiente($peticion);
            }
        }

        // Las peticiones de fondo no se redirigen: se responde en su idioma
        if ($peticion->expectsJson()) {
            return response()->json([
                'ok'    => false,
                'error' => 'Termina la configuración inicial para usar esta parte.',
            ], 409);
        }

        return redirect()->route('panel.bienvenida');
    }
}
