<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\ArticuloFoto;
use App\Models\Auditoria;
use App\Models\Familia;
use App\Models\Recurso;
use App\Models\Usuario;
use App\Services\GestorImagenes;
use Illuminate\Http\Request;

class ArticuloController extends Controller
{
    public function index(Request $peticion)
    {
        $consulta = Articulo::with(['familia', 'fotos'])
            ->when($peticion->filled('familia'), fn ($q) => $q->where('familia_id', $peticion->integer('familia')))
            ->when($peticion->filled('tipo'), fn ($q) => $q->where('tipo', $peticion->string('tipo')))
            ->when($peticion->filled('buscar'), function ($q) use ($peticion) {
                $texto = '%' . $peticion->string('buscar') . '%';
                $q->where(fn ($sub) => $sub->where('nombre', 'like', $texto)
                                           ->orWhere('codigo', 'like', $texto)
                                           ->orWhere('codigo_barras', 'like', $texto));
            })
            ->when($peticion->boolean('inactivos') === false, fn ($q) => $q->where('activo', true));

        return view('panel.catalogo.articulos', [
            'articulos' => $consulta->orderBy('familia_id')->orderBy('orden')->orderBy('nombre')->paginate(40)->withQueryString(),
            'familias'  => Familia::activas()->orderBy('orden')->get(),
            'filtros'   => $peticion->only(['familia', 'tipo', 'buscar', 'inactivos']),
        ]);
    }

    public function crear(Request $peticion)
    {
        $impuesto = (tenant('regimen_fiscal') ?? 'IGIC') === 'IGIC' ? 7.00 : 21.00;

        return $this->formulario(new Articulo([
            'tipo'                   => $peticion->string('tipo', 'SERVICIO'),
            'familia_id'             => $peticion->integer('familia') ?: null,
            'impuesto_pct'           => $impuesto,
            'duracion_min'           => 30,
            'activo'                 => true,
            'permite_reserva_online' => true,
            'requiere_confirmacion'  => true,
            'politica_pago'          => 'NINGUNO',
        ]));
    }

    public function editar(Articulo $articulo)
    {
        $articulo->load(['fotos', 'atributos', 'profesionales']);

        return $this->formulario($articulo);
    }

    protected function formulario(Articulo $articulo)
    {
        return view('panel.catalogo.articulo-form', [
            'articulo'      => $articulo,
            'familias'      => Familia::activas()->orderBy('orden')->get(),
            'recursos'      => Recurso::activos()->orderBy('nombre')->get(),
            'profesionales' => Usuario::activos()->profesionales()->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $peticion, ?Articulo $articulo = null)
    {
        $datos = $peticion->validate([
            'familia_id'         => ['required', 'exists:familias,id'],
            'tipo'               => ['required', 'in:SERVICIO,PRODUCTO,BONO,PACK'],
            'nombre'             => ['required', 'string', 'max:120'],
            'codigo'             => ['nullable', 'string', 'max:30'],
            'codigo_barras'      => ['nullable', 'string', 'max:40'],
            'descripcion'        => ['nullable', 'string', 'max:2000'],
            'descripcion_online' => ['nullable', 'string', 'max:2000'],

            'precio'             => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'impuesto_pct'       => ['required', 'numeric', 'min:0', 'max:99.99'],
            'coste'              => ['nullable', 'numeric', 'min:0'],

            'duracion_min'       => ['nullable', 'integer', 'min:0', 'max:600'],
            'tiempo_pausa_min'   => ['nullable', 'integer', 'min:0', 'max:600'],
            'tiempo_final_min'   => ['nullable', 'integer', 'min:0', 'max:600'],
            'recurso_id'         => ['nullable', 'exists:recursos,id'],

            'stock'              => ['nullable', 'numeric'],
            'stock_min'          => ['nullable', 'numeric', 'min:0'],

            'politica_pago'      => ['required', 'in:NINGUNO,FIANZA,TOTAL'],
            'fianza_importe'     => ['nullable', 'numeric', 'min:0'],
            'fianza_pct'         => ['nullable', 'numeric', 'min:0', 'max:100'],

            'sesiones'           => ['nullable', 'integer', 'min:1', 'max:999'],
            'caducidad_dias'     => ['nullable', 'integer', 'min:1', 'max:3650'],

            'orden'              => ['nullable', 'integer', 'min:0', 'max:999'],
            'color'              => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'profesionales'          => ['nullable', 'array'],
            'profesionales.*'        => ['exists:usuarios,id'],
            'atributos'              => ['nullable', 'array'],
            'atributos.*.clave'      => ['nullable', 'string', 'max:60'],
            'atributos.*.valor'      => ['nullable', 'string', 'max:255'],
            'fotos'                  => ['nullable', 'array', 'max:8'],
            'fotos.*'                => ['image', 'max:8192'],
        ]);

        $datos['control_stock']          = $peticion->boolean('control_stock');
        $datos['permite_reserva_online'] = $peticion->boolean('permite_reserva_online');
        $datos['requiere_confirmacion']  = $peticion->boolean('requiere_confirmacion');
        $datos['activo']                 = $peticion->boolean('activo');

        // Una fianza en porcentaje y otra en importe a la vez no tiene sentido
        if ($peticion->input('modo_fianza') === 'PCT') {
            $datos['fianza_importe'] = null;
        } else {
            $datos['fianza_pct'] = null;
        }

        $profesionales = $datos['profesionales'] ?? [];
        $atributos     = $datos['atributos'] ?? [];
        unset($datos['profesionales'], $datos['atributos'], $datos['fotos']);

        $esNuevo  = ! $articulo?->exists;
        $articulo = $articulo?->exists ? tap($articulo)->update($datos) : Articulo::create($datos);

        // Profesionales que lo realizan (vacío = cualquiera)
        $articulo->profesionales()->sync($profesionales);

        // Atributos: se reescriben enteros, es más simple y son pocos
        $articulo->atributos()->delete();
        $orden = 0;
        foreach ($atributos as $atributo) {
            if (blank($atributo['clave'] ?? null) || blank($atributo['valor'] ?? null)) {
                continue;
            }

            $articulo->atributos()->create([
                'clave' => $atributo['clave'],
                'valor' => $atributo['valor'],
                'orden' => ++$orden,
            ]);
        }

        if ($peticion->hasFile('fotos')) {
            $imagenes = new GestorImagenes();

            foreach ($peticion->file('fotos') as $fichero) {
                $imagenes->subirFotoArticulo($articulo, $fichero);
            }
        }

        Auditoria::registrar($esNuevo ? 'articulo_creado' : 'articulo_editado',
            'articulos', $articulo->id, ['nombre' => $articulo->nombre, 'precio' => $articulo->precio]);

        return redirect()->route('panel.catalogo.articulos.editar', $articulo)
            ->with('exito', $esNuevo ? 'Artículo creado.' : 'Artículo actualizado.');
    }

    public function borrar(Articulo $articulo)
    {
        Auditoria::registrar('articulo_borrado', 'articulos', $articulo->id, ['nombre' => $articulo->nombre]);

        // Borrado lógico: los tickets antiguos siguen apuntando aquí
        $articulo->delete();

        return redirect()->route('panel.catalogo.articulos')->with('exito', 'Artículo borrado.');
    }

    // ------------------------------------------------------------------
    // Fotos
    // ------------------------------------------------------------------

    public function borrarFoto(ArticuloFoto $foto)
    {
        $articuloId = $foto->articulo_id;
        $eraPrincipal = $foto->principal;

        $foto->borrarConFicheros();

        if ($eraPrincipal) {
            ArticuloFoto::where('articulo_id', $articuloId)->orderBy('orden')->first()?->marcarPrincipal();
        }

        return back()->with('exito', 'Foto eliminada.');
    }

    public function fotoPrincipal(ArticuloFoto $foto)
    {
        $foto->marcarPrincipal();

        return back()->with('exito', 'Foto principal actualizada.');
    }
}
