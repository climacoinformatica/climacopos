<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\FacturaPlataforma;
use App\Models\Plan;
use App\Services\GestorSuscripciones;
use Illuminate\Http\Request;

class SuscripcionController extends Controller
{
    public function __construct(
        protected GestorSuscripciones $gestor = new GestorSuscripciones(),
    ) {
    }

    public function index()
    {
        $empresa = tenant();

        return view('panel.ajustes.suscripcion', [
            'empresa'  => $empresa,
            'plan'     => $empresa->plan,

            /**
             * Solo los planes del producto SaaS.
             *
             * Antes se enseñaban los nueve —los de peluquerias, los de
             * restaurantes y los de gimnasios—, y un salon de belleza no
             * puede contratar el plan de un programa que no usa.
             *
             * Se busca por modalidad y no por slug: si algun dia hay otro
             * producto en la nube, seguira funcionando sin tocar esto.
             */
            'planes'   => Plan::deLaNube()->where('activo', true)
                              ->orderBy('orden')->get(),
            'facturas' => FacturaPlataforma::where('empresa_id', $empresa->id)
                            ->orderByDesc('created_at')->limit(12)->get(),
            'soloLectura' => GestorSuscripciones::enSoloLectura($empresa),
            'diasPrueba'  => $empresa->estado === 'PRUEBA' && $empresa->prueba_hasta
                             ? max(0, (int) now()->diffInDays($empresa->prueba_hasta, false))
                             : null,
        ]);
    }

    public function contratar(Request $peticion)
    {
        /**
         * OJO CON `exists` DENTRO DE UN TENANT
         *
         * La regla `exists:planes,id` usa la conexion POR DEFECTO, y
         * dentro del panel de un salon esa es la suya: buscaba la tabla
         * en climacopos_emp_N, donde no existe.
         *
         * Los planes viven en la base CENTRAL. Aqui se comprueba con el
         * modelo, que lleva CentralConnection y va a la base correcta.
         *
         * Es el mismo tipo de fallo que storage_path() apuntando a la
         * carpeta del tenant: algo que funciona fuera del contexto de
         * empresa y revienta dentro.
         */
        $datos = $peticion->validate([
            'plan_id' => ['required', 'integer'],
            'ciclo'   => ['required', 'in:MENSUAL,ANUAL'],
        ]);

        if (! Plan::whereKey($datos['plan_id'])->exists()) {
            return back()->with('error', 'Ese plan ya no esta disponible.');
        }

        $empresa = tenant();
        $plan = Plan::findOrFail($datos['plan_id']);

        /**
         * Se comprueba tambien aqui, no solo al pintar la lista.
         *
         * Si alguien manipula el formulario podria mandar el id de un
         * plan de gimnasios, y acabaria pagando por un programa que no
         * usa. La validacion de la vista no basta.
         */
        if (! $plan->producto?->esSaas()) {
            return back()->with('error',
                'Ese plan no corresponde a este programa.');
        }

        /**
         * El plan gratuito se activa sin pasar por Stripe.
         *
         * No hay nada que cobrar, asi que mandar al salon a una pasarela
         * de pago para un importe de cero euros no tiene sentido: la
         * propia Stripe rechazaria la sesion.
         */
        if ($plan->es_gratuito || (float) $plan->precio_mes <= 0) {
            $this->gestor->activar($empresa, $plan);

            Auditoria::registrar('suscripcion_gratuita', 'empresas', $empresa->id, [
                'plan' => $plan->nombre,
            ]);

            return back()->with('exito',
                'Ya estas en el plan ' . $plan->nombre . '. '
                . 'Puedes cambiar a otro cuando quieras.');
        }

        try {
            $url = $this->gestor->enlaceContratacion(
                $empresa,
                $plan,
                $datos['ciclo'],
                route('panel.suscripcion') . '?contratado=1',
                route('panel.suscripcion'),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auditoria::registrar('suscripcion_iniciada', 'empresas', $empresa->id, [
            'plan'  => $plan->nombre,
            'ciclo' => $datos['ciclo'],
        ]);

        return redirect()->away($url);
    }

    /** Portal de Stripe: tarjeta, facturas y cancelación. */
    public function portal()
    {
        try {
            $url = $this->gestor->portalFacturacion(tenant(), route('panel.suscripcion'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
