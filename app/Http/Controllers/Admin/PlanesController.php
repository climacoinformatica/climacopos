<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Planes de suscripcion, por producto.
 *
 * Hasta ahora los planes solo se podian crear por base de datos, asi que
 * en la practica no habia ninguno: el formulario de alta no ofrecia nada
 * que contratar.
 */
class PlanesController extends Controller
{
    public function index()
    {
        return view('admin.planes', [
            'productos' => Producto::with(['planes' => fn ($q) => $q->orderBy('orden')])
                                ->orderBy('orden')->get(),
        ]);
    }

    public function guardar(Request $peticion, Plan $plan)
    {
        $datos = $peticion->validate([
            'nombre'        => ['required', 'string', 'max:60'],
            'precio_mes'    => ['required', 'numeric', 'min:0', 'max:9999'],
            'soporte'       => ['required', 'in:NINGUNO,EMAIL,COMPLETO'],
            'soporte_texto' => ['nullable', 'string', 'max:200'],
            'descripcion'   => ['nullable', 'string', 'max:200'],
            'orden'         => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $datos['activo'] = $peticion->boolean('activo');

        $plan->update($datos);

        return back()->with('exito', 'Plan «' . $plan->nombre . '» guardado.');
    }

    public function crear(Request $peticion)
    {
        $datos = $peticion->validate([
            'producto_id'   => ['required', 'exists:productos,id'],
            'nombre'        => ['required', 'string', 'max:60'],
            'precio_mes'    => ['required', 'numeric', 'min:0', 'max:9999'],
            'soporte'       => ['required', 'in:NINGUNO,EMAIL,COMPLETO'],
            'soporte_texto' => ['nullable', 'string', 'max:200'],
        ]);

        $slug = Str::slug($datos['nombre']);

        /**
         * El slug es unico POR PRODUCTO, no globalmente.
         *
         * «basico» existe una vez en cada producto, y eso es correcto: son
         * planes distintos con el mismo nombre comercial.
         */
        if (Plan::where('producto_id', $datos['producto_id'])->where('slug', $slug)->exists()) {
            return back()->withInput()
                ->with('error', 'Ese producto ya tiene un plan con ese nombre.');
        }

        Plan::create($datos + [
            'slug'       => $slug,
            /**
             * Sin precio anual: solo se cobra al mes.
             *
             * Se deja a cero en lugar de quitar la columna, por si algun
             * dia se quiere ofrecer.
             */
            'precio_ano' => 0,

            // Sin limites: las funcionalidades van completas
            'max_profesionales'     => 999,
            'max_terminales'        => 999,
            'max_almacenamiento_mb' => 20480,
            'reservas_online'       => true,
            'pagos_online'          => true,
            'verifactu'             => true,
            'informes_avanzados'    => true,

            'orden'  => (Plan::where('producto_id', $datos['producto_id'])->max('orden') ?? 0) + 1,
            'activo' => true,
        ]);

        return back()->with('exito', 'Plan creado.');
    }

    public function borrar(Plan $plan)
    {
        /**
         * No se borra un plan que tenga salones.
         *
         * Quedarian sin plan asignado y el ciclo de cobro no sabria que
         * hacer con ellos. Se desactiva: deja de ofrecerse a clientes
         * nuevos, y los que ya lo tienen siguen igual.
         */
        if ($plan->empresas()->exists()) {
            $plan->update(['activo' => false]);

            return back()->with('exito',
                'Ese plan tiene salones contratados, asi que no se borra: '
                . 'se ha desactivado y ya no se ofrece a clientes nuevos.');
        }

        $plan->delete();

        return back()->with('exito', 'Plan borrado.');
    }

    // ------------------------------------------------------------------
    //  Stripe
    // ------------------------------------------------------------------

    /**
     * Crea en Stripe los productos y precios que falten.
     *
     * Sin esto, contratar un plan de pago falla con «no tiene precio
     * configurado en Stripe»: la pasarela no sabe que cobrar.
     */
    public function sincronizar(Request $peticion, ?Plan $plan = null)
    {
        $sincronizador = new \App\Services\SincronizadorStripe();

        // ---- Un plan concreto
        if ($plan && $plan->exists) {
            try {
                $sincronizador->sincronizar($plan);
            } catch (\Throwable $e) {
                return back()->with('error',
                    'No se pudo crear en Stripe: ' . $e->getMessage());
            }

            return back()->with('exito',
                'El plan «' . $plan->nombre . '» ya se puede contratar.');
        }

        // ---- Todos
        $resultado = $sincronizador->sincronizarTodos();

        if ($resultado['fallos'] !== []) {
            return back()->with('error',
                'Algunos planes no se pudieron crear: '
                . implode(' · ', array_map(
                    fn ($nombre, $error) => $nombre . ' (' . $error . ')',
                    array_keys($resultado['fallos']),
                    $resultado['fallos'],
                )));
        }

        return back()->with('exito',
            count($resultado['hechos']) . ' plan(es) listos para contratar.');
    }
}
