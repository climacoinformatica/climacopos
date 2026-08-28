<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Plan;
use App\Services\GestorSuscripciones;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function inicio()
    {
        $empresas = Empresa::with('plan')->orderByDesc('created_at')->get();

        return view('admin.inicio', [
            'empresas' => $empresas,
            'totales'  => [
                'total'      => $empresas->count(),
                'activas'    => $empresas->where('estado', 'ACTIVA')->count(),
                'prueba'     => $empresas->where('estado', 'PRUEBA')->count(),
                'suspendidas'=> $empresas->whereIn('estado', ['SUSPENDIDA', 'MOROSA'])->count(),
                'con_stripe' => $empresas->where('stripe_cobros_activos', true)->count(),
            ],
            'planes'   => Plan::withCount('empresas')->orderBy('orden')->get(),
        ]);
    }

    public function empresa(Empresa $empresa)
    {
        $datos = [];

        // Se entra en la base del salón solo para leer cuatro contadores
        tenancy()->initialize($empresa);

        try {
            $datos = [
                'usuarios'  => \App\Models\Usuario::count(),
                'articulos' => \App\Models\Articulo::count(),
                'clientes'  => \App\Models\Cliente::count(),
                'reservas'  => \App\Models\Reserva::count(),
                'tickets'   => \App\Models\Ticket::count(),
                'ventas'    => (float) \App\Models\Ticket::cobrados()->sum('total'),
            ];
        } catch (\Throwable $e) {
            $datos = ['error' => $e->getMessage()];
        } finally {
            tenancy()->end();
        }

        return view('admin.empresa', [
            'empresa'  => $empresa->load('plan', 'cuentas'),
            'datos'    => $datos,
            'facturas' => \App\Models\FacturaPlataforma::where('empresa_id', $empresa->id)
                            ->orderByDesc('created_at')->limit(12)->get(),
            'planes'   => Plan::where('activo', true)->orderBy('orden')->get(),
        ]);
    }

    /**
     * Cambio de estado a mano.
     *
     * Sirve para dar cortesias, reactivar tras un pago por transferencia o
     * ampliar una prueba. Queda registrado en el log de la plataforma.
     */
    public function cambiarEstado(Request $peticion, Empresa $empresa)
    {
        $datos = $peticion->validate([
            'estado'  => ['required', 'in:PRUEBA,ACTIVA,MOROSA,SUSPENDIDA,CANCELADA'],
            'plan_id' => ['nullable', 'exists:planes,id'],
            'dias'    => ['nullable', 'integer', 'min:1', 'max:365'],
            'motivo'  => ['required', 'string', 'max:255'],
        ]);

        $atributos = ['estado' => $datos['estado']];

        if (! empty($datos['plan_id'])) {
            $atributos['plan_id'] = $datos['plan_id'];
        }

        match ($datos['estado']) {
            'ACTIVA' => $atributos = array_merge($atributos, [
                'impagos'                => 0,
                'primer_impago_en'       => null,
                'suspension_efectiva_en' => null,
                'suspendida_en'          => null,
                'borrar_a_partir_de'     => null,
                'aviso_borrado_en'       => null,
                'suscripcion_hasta'      => isset($datos['dias'])
                                            ? now()->addDays($datos['dias']) : null,
            ]),

            'PRUEBA' => $atributos['prueba_hasta'] = now()->addDays($datos['dias'] ?? 14),

            'SUSPENDIDA' => $atributos = array_merge($atributos, [
                'suspendida_en'          => now(),
                // Nunca en mitad de la jornada
                'suspension_efectiva_en' => now()->addDay()->setTime(4, 0),
                'borrar_a_partir_de'     => now()->addDays(GestorSuscripciones::DIAS_HASTA_BORRADO),
            ]),

            default => null,
        };

        $empresa->forceFill($atributos)->save();

        \Illuminate\Support\Facades\Log::info('Estado de empresa cambiado a mano', [
            'empresa' => $empresa->slug,
            'estado'  => $datos['estado'],
            'motivo'  => $datos['motivo'],
            'admin'   => $peticion->attributes->get('superadmin')?->email,
        ]);

        return back()->with('exito', 'Estado actualizado.');
    }
}
