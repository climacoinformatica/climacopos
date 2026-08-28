<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Producto;

/**
 * Las paginas publicas de climacopos.com.
 *
 * Viven en el dominio central. Los subdominios los atiende el portal de
 * cada salon, que es otro conjunto de rutas: stancl/tenancy separa unas
 * de otras segun el dominio de la peticion.
 */
class PaginaController extends Controller
{
    public function inicio()
    {
        return view('web.inicio', [
            'productos' => Producto::activos()->with('versionActual')->get(),
        ]);
    }

    public function producto(string $slug)
    {
        $producto = Producto::where('slug', $slug)->where('activo', true)->firstOrFail();

        return view('web.producto', [
            'producto' => $producto->load('versionActual'),
            'otros'    => Producto::activos()->where('id', '!=', $producto->id)->get(),
        ]);
    }

    public function contacto()
    {
        return view('web.contacto');
    }

    public function legal(string $documento)
    {
        abort_unless(in_array($documento, ['aviso-legal', 'privacidad', 'condiciones'], true), 404);

        return view('web.legal.' . $documento);
    }
}
