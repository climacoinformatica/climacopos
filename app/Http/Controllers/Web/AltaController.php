<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Plan;
use App\Services\GestorAltas;
use Illuminate\Http\Request;

/**
 * Alta de un salon desde la web.
 *
 * Vive en el dominio central: cuando termina, el cliente se va a su
 * propio subdominio y ya no vuelve por aqui.
 */
class AltaController extends Controller
{
    public function __construct(
        protected GestorAltas $gestor = new GestorAltas(),
    ) {
    }

    public function formulario(Request $peticion)
    {
        $cuenta = auth('cuenta')->user();

        /**
         * Un salon por cuenta, de momento.
         *
         * Nada impide tecnicamente varios, pero abrir esa puerta sin
         * haber pensado la facturacion de cadenas complica mas de lo que
         * resuelve ahora.
         */
        $existente = Empresa::where('cuenta_id', $cuenta->id)->first();

        if ($existente) {
            return redirect()->route('web.area')
                ->with('error', 'Ya tienes un salón creado: ' . $existente->slug . '.climacopos.com');
        }

        return view('web.alta.formulario', [
            'cuenta'   => $cuenta,
            'propuesta'=> $this->gestor->proponerSlug($cuenta->empresa ?: $cuenta->nombre),
            /**
             * Solo los planes del producto SaaS.
             *
             * Un salon de belleza no puede contratar el plan del
             * programa de gimnasios: son productos distintos.
             */
            'planes'   => Plan::deLaNube()->where('activo', true)
                              ->orderBy('precio_mes')->get(),
        ]);
    }

    /** Comprobacion en vivo mientras el cliente escribe. */
    public function comprobar(Request $peticion)
    {
        return response()->json(
            $this->gestor->comprobarSlug((string) $peticion->input('slug', ''))
        );
    }

    public function crear(Request $peticion)
    {
        $cuenta = auth('cuenta')->user();

        $datos = $peticion->validate([
            'nombre_comercial' => ['required', 'string', 'max:120'],
            'slug'             => ['required', 'string', 'max:40'],
            'plan_id'          => ['nullable', 'exists:planes,id'],
            'acepta'           => ['accepted'],
        ], [
            'acepta.accepted' => 'Hay que aceptar las condiciones del servicio.',
        ]);

        if (Empresa::where('cuenta_id', $cuenta->id)->exists()) {
            return redirect()->route('web.area')
                ->with('error', 'Ya tienes un salón creado.');
        }

        try {
            $resultado = $this->gestor->crear(
                $cuenta,
                $datos['slug'],
                $datos['nombre_comercial'],
                isset($datos['plan_id']) ? Plan::find($datos['plan_id']) : null,
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        /**
         * Las credenciales viajan en la sesion, no en la URL.
         *
         * Una URL queda en el historial del navegador, en los registros
         * del servidor y en el «enviar enlace» de cualquiera. El PIN de
         * acceso al TPV no debe acabar ahi.
         */
        return redirect()->route('web.alta.listo')->with('alta', [
            'slug'     => $resultado['empresa']->slug,
            'nombre'   => $resultado['empresa']->nombre_comercial,
            'pin'      => $resultado['pin'],
            'password' => $resultado['password'],
        ]);
    }

    public function listo()
    {
        $alta = session('alta');

        if (! $alta) {
            return redirect()->route('web.area');
        }

        return view('web.alta.listo', ['alta' => $alta]);
    }
}
