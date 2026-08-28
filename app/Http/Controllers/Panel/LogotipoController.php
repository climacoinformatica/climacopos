<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Services\GestorLogotipo;
use Illuminate\Http\Request;

class LogotipoController extends Controller
{
    public function __construct(
        protected GestorLogotipo $gestor = new GestorLogotipo(),
    ) {
    }

    public function subir(Request $peticion)
    {
        $peticion->validate([
            'logo' => ['required', 'file', 'max:4096'],
        ], [
            'logo.required' => 'Elige una imagen.',
            'logo.max'      => 'La imagen pesa más de 4 MB.',
        ]);

        try {
            $this->gestor->subir($peticion->file('logo'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auditoria::registrar('logotipo_cambiado', 'empresas', tenant('id'), []);

        return back()->with('exito', 'Logotipo actualizado.');
    }

    /**
     * Sirve el logotipo del salon.
     *
     * No se enlaza al fichero directamente porque vive en el storage
     * privado del tenant, fuera de public/. Servirlo desde aqui evita
     * tener que crear un enlace simbolico por cada salon, y ademas
     * permitira mas adelante controlar quien puede verlo.
     */
    public function ver()
    {
        $ruta = tenant('logo');

        if (blank($ruta) || ! \Illuminate\Support\Facades\Storage::disk('tenant')->exists($ruta)) {
            abort(404);
        }

        return response(
            \Illuminate\Support\Facades\Storage::disk('tenant')->get($ruta),
            200,
            [
                'Content-Type'  => 'image/png',

                // Una semana: el logotipo cambia muy de vez en cuando, y
                // al cambiarlo el nombre del fichero es otro
                'Cache-Control' => 'public, max-age=604800',
            ],
        );
    }

    public function borrar()
    {
        $this->gestor->borrar();

        Auditoria::registrar('logotipo_borrado', 'empresas', tenant('id'), []);

        return back()->with('exito',
            'Logotipo quitado. Vuelve a verse el de CLIMACO POS.');
    }
}
