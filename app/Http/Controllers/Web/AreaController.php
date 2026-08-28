<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Descarga;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AreaController extends Controller
{
    public function inicio()
    {
        $cuenta = auth('cuenta')->user();

        return view('web.area.inicio', [
            'cuenta'    => $cuenta,
            'productos' => Producto::activos()->with('versionActual')->get(),

            // Salones del SaaS que ya tenga dados de alta
            'empresas'  => Empresa::where('cuenta_id', $cuenta->id)->get(),

            'descargas' => Descarga::where('cuenta_id', $cuenta->id)
                                ->with('version.producto')
                                ->orderByDesc('fecha')->limit(10)->get(),
        ]);
    }

    public function descargas()
    {
        return view('web.area.descargas', [
            'productos' => Producto::activos()
                                ->where('modalidad', 'INSTALABLE')
                                ->with(['versiones' => fn ($q) => $q->where('publica', true)])
                                ->get(),
        ]);
    }

    /**
     * Entrega del fichero.
     *
     * No se enlaza directamente al fichero en public/: se sirve desde
     * aqui para poder exigir sesion y dejar constancia de quien descarga
     * que version. Cuando un cliente llame con un problema, la primera
     * pregunta es siempre «que version tienes».
     */
    public function descargar(Request $peticion, ProductoVersion $version)
    {
        $cuenta = auth('cuenta')->user();

        abort_unless($version->publica, 404);

        if (! Storage::disk('descargas')->exists($version->fichero)) {
            return back()->with('error',
                'Ese fichero no está disponible ahora mismo. Escríbenos y lo resolvemos.');
        }

        Descarga::create([
            'cuenta_id'   => $cuenta->id,
            'version_id'  => $version->id,
            'ip'          => $peticion->ip(),
            'dispositivo' => mb_substr((string) $peticion->userAgent(), 0, 200),
            'fecha'       => now(),
        ]);

        $version->increment('descargas');

        return Storage::disk('descargas')->download(
            $version->fichero,
            $version->nombre_fichero,
        );
    }

    public function perfil()
    {
        return view('web.area.perfil', ['cuenta' => auth('cuenta')->user()]);
    }

    public function guardarPerfil(Request $peticion)
    {
        $cuenta = auth('cuenta')->user();

        $datos = $peticion->validate([
            'nombre'    => ['required', 'string', 'max:120'],
            'telefono'  => ['nullable', 'string', 'max:30'],
            'empresa'   => ['nullable', 'string', 'max:120'],
            'nif'       => ['nullable', 'string', 'max:20'],
            'provincia' => ['nullable', 'string', 'max:60'],
        ]);

        $datos['acepta_novedades'] = $peticion->boolean('acepta_novedades');

        $cuenta->update($datos);

        return back()->with('exito', 'Datos actualizados.');
    }
}
