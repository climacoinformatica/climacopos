<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\GestorProduccion;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parte de trabajo y produccion por profesional.
 *
 * QUIEN VE QUE
 *
 * Cada uno ve LO SUYO sin permisos especiales: es su trabajo y su dinero.
 * Solo quien gestiona personal ve el de todos.
 *
 * Va aparte del cierre de caja a proposito: el cierre cuadra el efectivo
 * y lo hace quien esta en el mostrador; esto es informacion laboral, y no
 * conviene que un companero vea lo que factura otro.
 */
class ProduccionController extends Controller
{
    public function __construct(
        protected GestorProduccion $gestor = new GestorProduccion(),
    ) {
    }

    public function index(Request $peticion)
    {
        $usuario = SesionSalon::usuario();
        $gestiona = $usuario->tienePermiso(Permisos::USUARIOS_GESTIONAR);

        [$desde, $hasta] = $this->rango($peticion);

        // Quien no gestiona personal solo se ve a si mismo
        $filtroUsuario = $gestiona
            ? ($peticion->integer('usuario_id') ?: null)
            : $usuario->id;

        return view('panel.produccion.index', [
            'datos'     => $this->gestor->periodo($desde, $hasta, $filtroUsuario),
            'gestiona'  => $gestiona,
            'usuarios'  => $gestiona
                           ? Usuario::activos()->profesionales()->orderBy('nombre')->get()
                           : collect(),
            'filtros'   => [
                'desde'      => $desde->toDateString(),
                'hasta'      => $hasta->toDateString(),
                'usuario_id' => $filtroUsuario,
                'atajo'      => $peticion->input('atajo'),
            ],
        ]);
    }

    /** Parte del dia, para imprimir al cerrar. */
    public function parte(Request $peticion)
    {
        $usuario = SesionSalon::usuario();
        $gestiona = $usuario->tienePermiso(Permisos::USUARIOS_GESTIONAR);

        $fecha = $peticion->filled('fecha')
            ? Carbon::parse($peticion->input('fecha'))
            : now();

        return view('panel.produccion.parte', [
            'datos' => $this->gestor->delDia($fecha, $gestiona ? null : $usuario->id),
            'fecha' => $fecha,
        ]);
    }

    public function detalle(Request $peticion, Usuario $usuario)
    {
        $actual = SesionSalon::usuario();

        // Solo lo tuyo, salvo que gestiones personal
        if ($usuario->id !== $actual->id
            && ! $actual->tienePermiso(Permisos::USUARIOS_GESTIONAR)) {
            abort(403);
        }

        [$desde, $hasta] = $this->rango($peticion);

        return view('panel.produccion.detalle', [
            'usuario' => $usuario,
            'lineas'  => $this->gestor->detalle($usuario->id, $desde, $hasta),
            'desde'   => $desde,
            'hasta'   => $hasta,
        ]);
    }

    /**
     * Rango de fechas.
     *
     * Con atajos, porque escribir dos fechas para ver «esta semana» es
     * pedir demasiado a quien esta cerrando el salon a las nueve.
     */
    protected function rango(Request $peticion): array
    {
        $atajo = $peticion->input('atajo');

        return match ($atajo) {
            'hoy'      => [now()->startOfDay(), now()->endOfDay()],
            'ayer'     => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'semana'   => [now()->startOfWeek(), now()->endOfWeek()],
            'semana_p' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'mes'      => [now()->startOfMonth(), now()->endOfMonth()],
            'mes_p'    => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            default    => [
                $peticion->filled('desde')
                    ? Carbon::parse($peticion->input('desde'))->startOfDay()
                    : now()->startOfMonth(),
                $peticion->filled('hasta')
                    ? Carbon::parse($peticion->input('hasta'))->endOfDay()
                    : now()->endOfDay(),
            ],
        };
    }

    /** Exportacion, con BOM y punto y coma para Excel en espanol. */
    public function exportar(Request $peticion)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::USUARIOS_GESTIONAR),
            403,
        );

        [$desde, $hasta] = $this->rango($peticion);

        $datos = $this->gestor->periodo($desde, $hasta);

        $nombre = 'produccion_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd') . '.csv';

        return new StreamedResponse(function () use ($datos) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, ['PRODUCCION POR PROFESIONAL'], ';');
            fputcsv($salida, ['Periodo',
                $datos['desde']->format('d/m/Y') . ' a ' . $datos['hasta']->format('d/m/Y')], ';');
            fputcsv($salida, [], ';');

            fputcsv($salida, ['Profesional', 'Servicios', 'Productos',
                'Facturado servicios', 'Facturado productos', 'Total',
                'Ticket medio', 'Comision'], ';');

            $euro = fn ($n) => number_format((float) $n, 2, ',', '');

            foreach ($datos['filas'] as $fila) {
                fputcsv($salida, [
                    $fila['usuario']->nombre,
                    $fila['servicios'],
                    $fila['productos'],
                    $euro($fila['fact_servicios']),
                    $euro($fila['fact_productos']),
                    $euro($fila['facturado']),
                    $euro($fila['medio']),
                    $euro($fila['comision']),
                ], ';');
            }

            fputcsv($salida, [], ';');
            fputcsv($salida, ['TOTAL',
                $datos['totales']['servicios'],
                $datos['totales']['productos'],
                '', '',
                $euro($datos['totales']['facturado']),
                '',
                $euro($datos['totales']['comisiones']),
            ], ';');

            if ($datos['totales']['sin_asignar'] > 0) {
                fputcsv($salida, [], ';');
                fputcsv($salida, ['Lineas sin profesional asignado',
                    $datos['totales']['sin_asignar'],
                    '', '', '',
                    $euro($datos['totales']['sin_asignar_imp'])], ';');
            }

            fclose($salida);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}
