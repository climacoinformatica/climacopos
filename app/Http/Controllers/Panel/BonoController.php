<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\Bono;
use App\Models\BonoPlantilla;
use App\Models\Cliente;
use App\Models\Familia;
use App\Models\Vale;
use App\Services\GestorBonos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

class BonoController extends Controller
{
    public function __construct(
        protected GestorBonos $gestor = new GestorBonos(),
    ) {
    }

    // ------------------------------------------------------------------
    // Plantillas
    // ------------------------------------------------------------------

    public function plantillas()
    {
        return view('panel.bonos.plantillas', [
            'plantillas' => BonoPlantilla::withCount('bonos')
                                ->with(['articulo', 'familia'])
                                ->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function crear()
    {
        return view('panel.bonos.plantilla-form', [
            'plantilla' => new BonoPlantilla(),
            'articulos' => Articulo::activos()->servicios()->orderBy('nombre')->get(),
            'familias'  => Familia::activas()->orderBy('nombre')->get(),
        ]);
    }

    public function editar(BonoPlantilla $plantilla)
    {
        return view('panel.bonos.plantilla-form', [
            'plantilla' => $plantilla,
            'articulos' => Articulo::activos()->servicios()->orderBy('nombre')->get(),
            'familias'  => Familia::activas()->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $peticion, ?BonoPlantilla $plantilla = null)
    {
        $datos = $peticion->validate([
            'nombre'          => ['required', 'string', 'max:120'],
            'descripcion'     => ['nullable', 'string', 'max:1000'],
            'modalidad'       => ['required', 'in:SESIONES,SALDO'],
            'precio'          => ['required', 'numeric', 'min:0.01'],
            'impuesto_pct'    => ['required', 'numeric', 'min:0', 'max:100'],
            'num_sesiones'    => ['nullable', 'integer', 'min:1', 'max:999'],
            'saldo_otorgado'  => ['nullable', 'numeric', 'min:0'],
            'articulo_id'     => ['nullable', 'exists:articulos,id'],
            'familia_id'      => ['nullable', 'exists:familias,id'],
            'caducidad_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'color'           => ['nullable', 'string', 'max:9'],
            'orden'           => ['nullable', 'integer', 'min:0'],
        ]);

        // Coherencia según la modalidad
        if ($datos['modalidad'] === 'SESIONES') {
            if (blank($datos['num_sesiones'] ?? null)) {
                return back()->withInput()->with('error',
                    'Un bono por sesiones necesita saber cuántas incluye.');
            }

            $datos['saldo_otorgado'] = null;
        } else {
            if (blank($datos['saldo_otorgado'] ?? null)) {
                return back()->withInput()->with('error',
                    'Un bono de saldo necesita saber cuánto saldo otorga.');
            }

            $datos['num_sesiones'] = null;

            /**
             * Un bono de saldo no se ata a un servicio: si se limitara a
             * uno solo, sería un bono por sesiones con otro nombre.
             */
            $datos['articulo_id'] = null;
        }

        $datos['activo']        = $peticion->boolean('activo');
        $datos['vender_online'] = $peticion->boolean('vender_online');

        if ($plantilla && $plantilla->exists) {
            $plantilla->update($datos);
            $mensaje = 'Bono actualizado.';
        } else {
            $plantilla = BonoPlantilla::create($datos);
            $mensaje = 'Bono creado.';

            /**
             * Se crea también el artículo que lo vende.
             *
             * Sin esto, el salón tendría que acordarse de crear a mano un
             * artículo con el mismo precio y enlazarlo. Es un paso que se
             * olvida siempre y deja el bono invendible desde el TPV.
             */
            $this->crearArticuloDeVenta($plantilla);
        }

        return redirect()->route('panel.bonos.plantillas')->with('exito', $mensaje);
    }

    protected function crearArticuloDeVenta(BonoPlantilla $plantilla): void
    {
        $familia = Familia::firstOrCreate(
            ['nombre' => 'Bonos'],
            ['tipo' => 'PRODUCTO', 'color' => '#8b5cf6', 'orden' => 99],
        );

        Articulo::create([
            'familia_id'        => $familia->id,
            'tipo'              => 'PRODUCTO',
            'nombre'            => $plantilla->nombre,
            'precio'            => $plantilla->precio,
            'impuesto_pct'      => $plantilla->impuesto_pct,
            'bono_plantilla_id' => $plantilla->id,
            'color'             => $plantilla->color,
            'control_stock'     => false,
            'activo'            => true,
        ]);
    }

    public function borrar(BonoPlantilla $plantilla)
    {
        if ($plantilla->bonos()->where('estado', 'ACTIVO')->exists()) {
            return back()->with('error',
                'Hay bonos activos de este tipo. Desactívalo en vez de borrarlo: '
                . 'las clientas que lo compraron podrán seguir usándolo.');
        }

        $plantilla->delete();

        return back()->with('exito', 'Bono eliminado.');
    }

    // ------------------------------------------------------------------
    // Bonos vendidos
    // ------------------------------------------------------------------

    public function vendidos(Request $peticion)
    {
        $consulta = Bono::with(['plantilla', 'cliente'])->orderByDesc('id');

        if ($peticion->filled('buscar')) {
            $texto = $peticion->string('buscar')->toString();

            $consulta->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', "%{$texto}%")
                  ->orWhereHas('cliente', fn ($c) => $c
                      ->where('nombre', 'like', "%{$texto}%")
                      ->orWhere('apellidos', 'like', "%{$texto}%")
                      ->orWhere('telefono', 'like', "%{$texto}%"));
            });
        }

        if ($peticion->filled('estado')) {
            $consulta->where('estado', $peticion->input('estado'));
        }

        return view('panel.bonos.vendidos', [
            'bonos'   => $consulta->paginate(40)->withQueryString(),
            'filtros' => $peticion->only(['buscar', 'estado']),
            'proximos'=> Bono::utilizables()
                            ->whereNotNull('caduca_el')
                            ->where('caduca_el', '<=', now()->addMonth()->toDateString())
                            ->with('cliente')->get(),
        ]);
    }

    public function ver(Bono $bono)
    {
        return view('panel.bonos.detalle', [
            'bono' => $bono->load(['plantilla', 'cliente', 'movimientos.usuario', 'movimientos.ticket']),
        ]);
    }

    /** Emisión manual, para bonos vendidos fuera del TPV. */
    public function emitir(Request $peticion)
    {
        $datos = $peticion->validate([
            'plantilla_id' => ['required', 'exists:bonos_plantillas,id'],
            'cliente_id'   => ['required', 'exists:clientes,id'],
        ]);

        $bono = $this->gestor->vender(
            BonoPlantilla::findOrFail($datos['plantilla_id']),
            Cliente::findOrFail($datos['cliente_id']),
        );

        return redirect()->route('panel.bonos.ver', $bono)
            ->with('exito', 'Bono ' . $bono->codigo . ' emitido.');
    }

    public function anular(Request $peticion, Bono $bono)
    {
        $peticion->validate(['motivo' => ['required', 'string', 'max:255']]);

        $bono->update([
            'estado'         => 'ANULADO',
            'observaciones'  => trim(($bono->observaciones ? $bono->observaciones . "\n" : '')
                                . 'Anulado: ' . $peticion->input('motivo')),
        ]);

        \App\Models\Auditoria::registrar('bono_anulado', 'bonos', $bono->id, [
            'codigo' => $bono->codigo,
            'motivo' => $peticion->input('motivo'),
        ]);

        return back()->with('exito', 'Bono anulado.');
    }

    // ------------------------------------------------------------------
    // Monedero
    // ------------------------------------------------------------------

    public function recargar(Request $peticion, Cliente $cliente)
    {
        $datos = $peticion->validate([
            'importe'  => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'tipo'     => ['required', 'in:RECARGA,REGALO,AJUSTE'],
            'concepto' => ['nullable', 'string', 'max:200'],
        ]);

        $this->gestor->recargarMonedero(
            $cliente,
            (float) $datos['importe'],
            $datos['tipo'],
            $datos['concepto'] ?? null,
        );

        return back()->with('exito',
            'Monedero recargado. Saldo: '
            . number_format((float) $cliente->fresh()->saldo_monedero, 2, ',', '.') . ' €.');
    }

    // ------------------------------------------------------------------
    // Vales
    // ------------------------------------------------------------------

    public function vales(Request $peticion)
    {
        $consulta = Vale::with('cliente')->orderByDesc('id');

        if ($peticion->filled('buscar')) {
            $consulta->where('codigo', 'like', '%' . $peticion->input('buscar') . '%');
        }

        return view('panel.bonos.vales', [
            'vales'   => $consulta->paginate(40)->withQueryString(),
            'filtros' => $peticion->only('buscar'),
            'total'   => (float) Vale::utilizables()->sum('importe_restante'),
        ]);
    }

    public function emitirVale(Request $peticion)
    {
        $datos = $peticion->validate([
            'importe'    => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'meses'      => ['nullable', 'integer', 'min:1', 'max:60'],
            'concepto'   => ['nullable', 'string', 'max:200'],
        ]);

        $vale = $this->gestor->emitirVale(
            (float) $datos['importe'],
            'MANUAL',
            isset($datos['cliente_id']) ? Cliente::find($datos['cliente_id']) : null,
            null,
            $datos['meses'] ?? 12,
            $datos['concepto'] ?? null,
        );

        return back()->with('exito', 'Vale ' . $vale->codigo . ' emitido por '
            . number_format((float) $vale->importe_inicial, 2, ',', '.') . ' €.');
    }

    /** Consulta rápida desde el TPV. */
    public function consultarVale(Request $peticion)
    {
        $vale = $this->gestor->buscarVale($peticion->input('codigo', ''));

        if (! $vale) {
            return response()->json(['ok' => false, 'error' => 'No existe ningún vale con ese código.'], 404);
        }

        if (! $vale->estaDisponible()) {
            return response()->json([
                'ok'    => false,
                'error' => $vale->haCaducado()
                    ? 'Ese vale caducó el ' . $vale->caduca_el->format('d/m/Y') . '.'
                    : 'Ese vale ya no tiene saldo.',
            ], 422);
        }

        return response()->json([
            'ok'        => true,
            'codigo'    => $vale->codigo,
            'restante'  => (float) $vale->importe_restante,
            'caduca'    => $vale->caduca_el?->format('d/m/Y'),
        ]);
    }
}
