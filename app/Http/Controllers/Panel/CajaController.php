<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\CierreJornada;
use App\Models\Ticket;
use App\Services\GestorCierre;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CajaController extends Controller
{
    public function __construct(
        protected GestorCierre $gestor = new GestorCierre(),
    ) {
    }

    public function index()
    {
        return view('panel.caja.index', [
            'resumen'  => $this->gestor->resumen(),
            'ultimos'  => CierreJornada::with('usuario')->orderByDesc('fecha_fin')->limit(10)->get(),
        ]);
    }

    public function movimiento(Request $peticion)
    {
        $datos = $peticion->validate([
            'tipo'    => ['required', 'in:APERTURA,ENTRADA,SALIDA'],
            'importe' => ['required', 'numeric', 'min:0.01'],
            'motivo'  => ['required', 'string', 'max:160'],
        ]);

        $this->gestor->movimiento(
            $datos['tipo'],
            (float) $datos['importe'],
            $datos['motivo'],
            SesionSalon::usuario(),
        );

        return back()->with('exito', 'Movimiento registrado.');
    }

    public function cerrar(Request $peticion)
    {
        $datos = $peticion->validate([
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
        ]);

        $resumen = $this->gestor->resumen();

        if ($resumen['num_tickets'] === 0 && $resumen['movimientos']->isEmpty()) {
            return back()->with('error', 'No hay nada que cerrar.');
        }

        /**
         * Se cierra Y se imprime.
         *
         * Si la impresora falla, el cierre sigue hecho: se anota en el
         * registro y el papel se saca despues desde esta misma pantalla.
         * Un problema de papel no puede tumbar un cierre que ya cuadro la
         * caja y marco los tickets.
         */
        $cierre = $this->gestor->cerrarEImprimir(
            SesionSalon::usuario(),
            (float) $datos['efectivo_contado'],
            $datos['observaciones'] ?? null,
        );

        return redirect()->route('panel.caja.cierre', $cierre)
            ->with('exito', 'Jornada cerrada. Ya salen el cierre y el parte de trabajo.');
    }

    /**
     * Vuelve a sacar el cierre y el parte.
     *
     * Hace falta mas de lo que parece: la impresora se queda sin papel a
     * mitad, o el conector estaba parado, y el cierre ya no se puede
     * repetir porque los tickets estan marcados.
     */
    public function reimprimir(Request $peticion, CierreJornada $cierre)
    {
        $impresion = new \App\Services\GestorImpresion();

        try {
            if ($peticion->input('que') === 'parte') {
                $impresion->parteTrabajo($cierre->fecha_fin);
                $mensaje = 'Parte de trabajo enviado a la impresora.';
            } else {
                $impresion->cierre($cierre);
                $mensaje = 'Cierre enviado a la impresora.';
            }
        } catch (\Throwable $e) {
            return back()->with('error',
                'No se ha podido enviar a la impresora. Comprueba que el '
                . 'conector esta funcionando en este equipo.');
        }

        return back()->with('exito', $mensaje);
    }

    public function verCierre(CierreJornada $cierre)
    {
        return view('panel.caja.cierre', [
            'cierre'  => $cierre->load('usuario'),
            'tickets' => $cierre->tickets()->with('cobros')->get(),
        ]);
    }

    // ------------------------------------------------------------------
    // Documentos de formacion
    // ------------------------------------------------------------------

    /**
     * Fichero de consulta de los documentos de practicas.
     *
     * Estos tickets no han entrado en ningun cierre ni en ningun informe:
     * el global scope los mantiene fuera de todo. Aqui es el unico sitio
     * donde se ven, y desde donde se pueden borrar.
     */
    public function formacion(Request $peticion)
    {
        $consulta = Ticket::soloFormacion()->with(['usuario', 'lineas', 'cobros']);

        if ($peticion->filled('desde')) {
            $consulta->whereDate('fecha', '>=', $peticion->input('desde'));
        }

        if ($peticion->filled('hasta')) {
            $consulta->whereDate('fecha', '<=', $peticion->input('hasta'));
        }

        if ($peticion->filled('usuario_id')) {
            $consulta->where('usuario_id', $peticion->integer('usuario_id'));
        }

        $tickets = $consulta->orderByDesc('fecha')->paginate(50)->withQueryString();

        return view('panel.caja.formacion', [
            'tickets'  => $tickets,
            'usuarios' => \App\Models\Usuario::withTrashed()->where('en_formacion', true)->get(),
            'filtros'  => $peticion->only(['desde', 'hasta', 'usuario_id']),
            'total'    => (float) Ticket::soloFormacion()->sum('total'),
        ]);
    }

    /** Exporta a JSON antes de borrar, por si el propietario quiere guardarlos. */
    public function exportarFormacion()
    {
        $tickets = Ticket::soloFormacion()->with(['usuario', 'lineas', 'cobros'])->get();

        $datos = $tickets->map(fn (Ticket $t) => [
            'referencia' => $t->referencia(),
            'fecha'      => $t->fecha->format('Y-m-d H:i:s'),
            'usuario'    => $t->usuario?->nombre,
            'total'      => (float) $t->total,
            'lineas'     => $t->lineas->map(fn ($l) => [
                'descripcion' => $l->descripcion,
                'cantidad'    => (float) $l->cantidad,
                'importe'     => (float) $l->importe,
            ]),
            'cobros'     => $t->cobros->map(fn ($c) => [
                'medio'   => $c->medio,
                'importe' => (float) $c->importe,
            ]),
        ]);

        $nombre = 'formacion_' . now()->format('Ymd_His') . '.json';

        return response()->json([
            'empresa'   => tenant('nombre_comercial'),
            'exportado' => now()->toDateTimeString(),
            'aviso'     => 'DOCUMENTOS DE FORMACION - SIN VALOR FISCAL',
            'tickets'   => $datos,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function borrarFormacion(Request $peticion)
    {
        $usuario = SesionSalon::usuario();

        if (! $usuario->tienePermiso(Permisos::FORMACION_BORRAR)) {
            return back()->with('error', 'Tu perfil no permite borrar documentos de formación.');
        }

        $datos = $peticion->validate([
            'ambito' => ['required', 'in:TODO,RANGO,UNO'],
            'desde'  => ['nullable', 'date'],
            'hasta'  => ['nullable', 'date'],
            'ticket_id' => ['nullable', 'integer'],
        ]);

        $consulta = Ticket::soloFormacion();

        if ($datos['ambito'] === 'RANGO') {
            $consulta->whereDate('fecha', '>=', $datos['desde'] ?? '1900-01-01')
                     ->whereDate('fecha', '<=', $datos['hasta'] ?? now()->toDateString());
        } elseif ($datos['ambito'] === 'UNO') {
            $consulta->where('id', $datos['ticket_id']);
        }

        $cuantos = (clone $consulta)->count();
        $consulta->delete();   // en cascada se llevan líneas y cobros

        Auditoria::registrar('formacion_borrada', 'tickets', null, [
            'ambito'  => $datos['ambito'],
            'cuantos' => $cuantos,
        ], $usuario->id);

        return back()->with('exito', "Se han borrado {$cuantos} documento(s) de formación.");
    }
}
