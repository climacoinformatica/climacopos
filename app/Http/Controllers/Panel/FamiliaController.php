<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Familia;
use App\Services\GestorImagenes;
use Illuminate\Http\Request;

class FamiliaController extends Controller
{
    public function index()
    {
        return view('panel.catalogo.familias', [
            'familias' => Familia::with('padre')
                ->withCount('articulos')
                ->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function crear()
    {
        return view('panel.catalogo.familia-form', [
            'familia' => new Familia(['tipo' => 'SERVICIO', 'color' => '#6366f1', 'activa' => true, 'visible_online' => true]),
            'padres'  => Familia::raiz()->orderBy('nombre')->get(),
        ]);
    }

    public function editar(Familia $familia)
    {
        return view('panel.catalogo.familia-form', [
            'familia' => $familia,
            'padres'  => Familia::raiz()->where('id', '!=', $familia->id)->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $peticion, ?Familia $familia = null, GestorImagenes $imagenes = new GestorImagenes())
    {
        $datos = $peticion->validate([
            'nombre'           => ['required', 'string', 'max:80'],
            'tipo'             => ['required', 'in:SERVICIO,PRODUCTO,AMBOS'],
            'familia_padre_id' => ['nullable', 'exists:familias,id'],
            'color'            => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'descripcion'      => ['nullable', 'string', 'max:1000'],
            'orden'            => ['nullable', 'integer', 'min:0', 'max:999'],
            'imagen'           => ['nullable', 'image', 'max:8192'],
        ]);

        $datos['visible_online'] = $peticion->boolean('visible_online');
        $datos['activa']         = $peticion->boolean('activa');
        $datos['orden']          = $datos['orden'] ?? 0;

        if ($peticion->hasFile('imagen')) {
            $datos['imagen'] = $imagenes->subirImagenSimple($peticion->file('imagen'), 'familias');
        } else {
            unset($datos['imagen']);
        }

        $esNueva = ! $familia?->exists;
        $familia = $familia?->exists ? tap($familia)->update($datos) : Familia::create($datos);

        Auditoria::registrar($esNueva ? 'familia_creada' : 'familia_editada',
            'familias', $familia->id, ['nombre' => $familia->nombre]);

        return redirect()->route('panel.catalogo.familias')
            ->with('exito', $esNueva ? 'Familia creada.' : 'Familia actualizada.');
    }

    public function borrar(Familia $familia)
    {
        if (! $familia->puedeBorrarse()) {
            return back()->with('error',
                'No se puede borrar: la familia tiene artículos o subfamilias. Desactívala en su lugar.');
        }

        Auditoria::registrar('familia_borrada', 'familias', $familia->id, ['nombre' => $familia->nombre]);
        $familia->delete();

        return redirect()->route('panel.catalogo.familias')->with('exito', 'Familia borrada.');
    }
}
