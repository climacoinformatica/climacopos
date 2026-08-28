<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\FichaTecnica;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\GestorBonos;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $peticion)
    {
        $consulta = Cliente::query()->with('profesionalHabitual');

        if ($peticion->filled('buscar')) {
            $texto = $peticion->string('buscar')->toString();

            $consulta->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', "%{$texto}%")
                  ->orWhere('apellidos', 'like', "%{$texto}%")
                  ->orWhere('telefono', 'like', "%{$texto}%")
                  ->orWhere('email', 'like', "%{$texto}%")
                  ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellidos, '')) LIKE ?", ["%{$texto}%"]);
            });
        }

        match ($peticion->input('filtro')) {
            'con_saldo'   => $consulta->where('saldo_monedero', '>', 0),
            'con_avisos'  => $consulta->whereNotNull('avisos_ficha'),
            'inactivos'   => $consulta->where('ultima_visita', '<', now()->subMonths(6)),
            'bloqueados'  => $consulta->where('bloqueado', true),
            default       => null,
        };

        return view('panel.clientes.index', [
            'clientes' => $consulta->orderByDesc('ultima_visita')->paginate(40)->withQueryString(),
            'filtros'  => $peticion->only(['buscar', 'filtro']),
        ]);
    }

    public function ver(Cliente $cliente)
    {
        $tickets = Ticket::where('cliente_id', $cliente->id)
            ->with('lineas')
            ->orderByDesc('fecha')->limit(30)->get();

        return view('panel.clientes.ficha', [
            'cliente'    => $cliente->load('profesionalHabitual'),
            'fichas'     => FichaTecnica::where('cliente_id', $cliente->id)
                                ->with('usuario')->orderByDesc('fecha')->get(),
            'tickets'    => $tickets,
            'reservas'   => Reserva::where('cliente_id', $cliente->id)
                                ->orderByDesc('fecha')->limit(20)->get(),
            'bonos'      => $cliente->bonos()->with('plantilla')->get(),
            'movimientos'=> $cliente->movimientosMonedero()->limit(20)->get(),
            'vales'      => $cliente->vales()->get(),

            /** Gasto acumulado: el dato que decide si merece un detalle. */
            'gastado'    => round((float) $tickets->where('estado', 'COBRADO')->sum('total'), 2),
        ]);
    }

    public function editar(Cliente $cliente)
    {
        return view('panel.clientes.form', [
            'cliente'       => $cliente,
            'profesionales' => Usuario::activos()->profesionales()->orderBy('nombre')->get(),
        ]);
    }

    public function crear()
    {
        return view('panel.clientes.form', [
            'cliente'       => new Cliente(),
            'profesionales' => Usuario::activos()->profesionales()->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $peticion, ?Cliente $cliente = null)
    {
        $datos = $peticion->validate([
            'nombre'       => ['required', 'string', 'max:80'],
            'apellidos'    => ['nullable', 'string', 'max:120'],
            'telefono'     => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:160'],
            'fecha_nac'    => ['nullable', 'date', 'before:today'],
            'direccion'    => ['nullable', 'string', 'max:200'],

            'avisos_ficha' => ['nullable', 'string', 'max:300'],
            'alergias'     => ['nullable', 'string', 'max:1000'],
            'notas'        => ['nullable', 'string', 'max:2000'],
            'preferencias' => ['nullable', 'string', 'max:1000'],
            'tipo_cabello' => ['nullable', 'string', 'max:60'],

            'profesional_habitual_id' => ['nullable', 'exists:usuarios,id'],
        ]);

        $datos['acepta_marketing'] = $peticion->boolean('acepta_marketing');
        $datos['bloqueado']        = $peticion->boolean('bloqueado');

        if ($cliente && $cliente->exists) {
            $cliente->update($datos);
            $mensaje = 'Ficha actualizada.';
        } else {
            $datos['fecha_alta'] = now();
            $cliente = Cliente::create($datos);
            $mensaje = 'Cliente creado.';
        }

        return redirect()->route('panel.clientes.ver', $cliente)->with('exito', $mensaje);
    }

    // ------------------------------------------------------------------
    // Fichas técnicas
    // ------------------------------------------------------------------

    public function nuevaFicha(Request $peticion, Cliente $cliente)
    {
        /**
         * Se puede partir de una ficha anterior.
         *
         * Repetir la última fórmula es la operación más frecuente, y
         * teclearla de nuevo invita a equivocarse en un tono.
         */
        $borrador = null;

        if ($peticion->filled('repetir')) {
            $anterior = FichaTecnica::where('cliente_id', $cliente->id)
                ->find($peticion->integer('repetir'));

            $borrador = $anterior?->comoBorrador();
        }

        return view('panel.clientes.ficha-form', [
            'cliente'  => $cliente,
            'ficha'    => new FichaTecnica($borrador ?? []),
            'esCopia'  => $borrador !== null,
        ]);
    }

    public function editarFicha(Cliente $cliente, FichaTecnica $ficha)
    {
        abort_unless($ficha->cliente_id === $cliente->id, 404);

        return view('panel.clientes.ficha-form', [
            'cliente' => $cliente,
            'ficha'   => $ficha,
            'esCopia' => false,
        ]);
    }

    public function guardarFicha(Request $peticion, Cliente $cliente, ?FichaTecnica $ficha = null)
    {
        $datos = $peticion->validate([
            'tipo'            => ['required', 'in:' . implode(',', array_keys(FichaTecnica::TIPOS))],
            'titulo'          => ['nullable', 'string', 'max:160'],
            'fecha'           => ['nullable', 'date'],
            'oxigenante'      => ['nullable', 'string', 'max:40'],
            'tiempo_pose_min' => ['nullable', 'integer', 'min:1', 'max:600'],
            'proceso'         => ['nullable', 'string', 'max:2000'],
            'resultado'       => ['nullable', 'string', 'max:2000'],
            'observaciones'   => ['nullable', 'string', 'max:2000'],
            'valoracion'      => ['nullable', 'integer', 'min:1', 'max:5'],

            'formula'              => ['nullable', 'array', 'max:12'],
            'formula.*.marca'      => ['nullable', 'string', 'max:60'],
            'formula.*.tono'       => ['nullable', 'string', 'max:40'],
            'formula.*.cantidad'   => ['nullable', 'numeric', 'min:0'],
            'formula.*.unidad'     => ['nullable', 'string', 'max:10'],
        ]);

        // Fuera las filas vacías de la fórmula
        $datos['formula'] = collect($peticion->input('formula', []))
            ->filter(fn ($fila) => filled($fila['tono'] ?? null) || filled($fila['marca'] ?? null))
            ->map(fn ($fila) => [
                'marca'    => $fila['marca'] ?? null,
                'tono'     => $fila['tono'] ?? null,
                'cantidad' => filled($fila['cantidad'] ?? null) ? (float) $fila['cantidad'] : null,
                'unidad'   => $fila['unidad'] ?? 'g',
            ])
            ->values()
            ->all();

        $datos['cliente_id'] = $cliente->id;
        $datos['fecha'] ??= now();

        if ($ficha && $ficha->exists) {
            abort_unless($ficha->cliente_id === $cliente->id, 404);

            $ficha->update($datos);
            $mensaje = 'Ficha técnica actualizada.';
        } else {
            $datos['usuario_id'] = SesionSalon::usuario()?->id;

            $ficha = FichaTecnica::create($datos);
            $mensaje = 'Ficha técnica guardada.';
        }

        return redirect()->route('panel.clientes.ver', $cliente)->with('exito', $mensaje);
    }

    public function borrarFicha(Cliente $cliente, FichaTecnica $ficha)
    {
        abort_unless($ficha->cliente_id === $cliente->id, 404);

        if (! SesionSalon::usuario()->tienePermiso(Permisos::CLIENTES_EDITAR)) {
            return back()->with('error', 'Tu perfil no permite borrar fichas técnicas.');
        }

        Auditoria::registrar('ficha_tecnica_borrada', 'fichas_tecnicas', $ficha->id, [
            'cliente' => $cliente->nombreCompleto(),
            'fecha'   => $ficha->fecha->format('d/m/Y'),
        ]);

        $ficha->delete();

        return back()->with('exito', 'Ficha técnica eliminada.');
    }

    // ------------------------------------------------------------------
    // Monedero desde la ficha
    // ------------------------------------------------------------------

    public function recargar(Request $peticion, Cliente $cliente)
    {
        $datos = $peticion->validate([
            'importe'  => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'tipo'     => ['required', 'in:RECARGA,REGALO,AJUSTE'],
            'concepto' => ['nullable', 'string', 'max:200'],
        ]);

        (new GestorBonos())->recargarMonedero(
            $cliente,
            (float) $datos['importe'],
            $datos['tipo'],
            $datos['concepto'] ?? null,
        );

        return back()->with('exito', 'Saldo actualizado: '
            . number_format((float) $cliente->fresh()->saldo_monedero, 2, ',', '.') . ' €.');
    }
}
