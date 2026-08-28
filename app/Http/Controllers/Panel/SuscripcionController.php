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
            'planes'   => Plan::where('activo', true)->orderBy('orden')->get(),
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
        $datos = $peticion->validate([
            'plan_id' => ['required', 'exists:planes,id'],
            'ciclo'   => ['required', 'in:MENSUAL,ANUAL'],
        ]);

        $empresa = tenant();
        $plan = Plan::findOrFail($datos['plan_id']);

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
