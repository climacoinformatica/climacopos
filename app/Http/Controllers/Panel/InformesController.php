<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\GeneradorInformes;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformesController extends Controller
{
    /** Informes disponibles y qué permiso pide cada uno. */
    public const INFORMES = [
        'resumen'     => ['Resumen', 'Ventas, ticket medio y comparación con el periodo anterior'],
        'evolucion'   => ['Evolución', 'Por día, por hora y por día de la semana'],
        'catalogo'    => ['Catálogo', 'Familias, artículos y servicios frente a productos'],
        'personas'    => ['Profesionales', 'Ventas y comisiones por profesional'],
        'clientes'    => ['Clientes', 'Nuevos, recurrentes, mejores e inactivos'],
        'agenda'      => ['Agenda', 'Ocupación, reservas online y plantones'],
        'control'     => ['Control', 'Invitaciones, anulaciones y libro de facturas'],
        'stock'       => ['Stock', 'Existencias y artículos bajo mínimo'],
    ];

    public function index(Request $peticion)
    {
        [$desde, $hasta, $rango] = $this->rango($peticion);

        $informe = $peticion->string('informe', 'resumen')->toString();

        if (! array_key_exists($informe, self::INFORMES)) {
            $informe = 'resumen';
        }

        $generador = GeneradorInformes::entre($desde, $hasta);
        $usuario   = SesionSalon::usuario();

        // Quien solo tiene 'informes.ver_propios' ve únicamente lo suyo
        $soloPropios = ! $usuario->tienePermiso(Permisos::INFORMES_VER)
                       && $usuario->tienePermiso(Permisos::INFORMES_VER_PROPIOS);

        return view('panel.informes.index', [
            'informe'     => $informe,
            'informes'    => self::INFORMES,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'rango'       => $rango,
            'generador'   => $generador,
            'datos'       => $this->datos($generador, $informe, $soloPropios, $usuario),
            'soloPropios' => $soloPropios,
        ]);
    }

    protected function datos(GeneradorInformes $g, string $informe, bool $soloPropios, $usuario): array
    {
        $datos = match ($informe) {
            'evolucion' => [
                'por_dia'        => $g->porDia(),
                'por_hora'       => $g->porHora(),
                'por_dia_semana' => $g->porDiaSemana(),
            ],
            'catalogo' => [
                'familias'  => $g->porFamilia(),
                'articulos' => $g->porArticulo(),
                'tipos'     => $g->serviciosVsProductos(),
            ],
            'personas' => [
                'profesionales' => $g->porProfesional(),
                'medios'        => $g->porMedioPago(),
            ],
            'clientes' => [
                'resumen'    => $g->clientes(),
                'mejores'    => $g->mejoresClientes(),
                'inactivos'  => $g->clientesInactivos(),
            ],
            'agenda' => [
                'ocupacion' => $g->ocupacion(),
                'reservas'  => $g->reservas(),
            ],
            'control' => [
                'invitaciones' => $g->invitaciones(),
                'anulaciones'  => $g->anulaciones(),
                'libro'        => $g->libroFacturas(),
            ],
            'stock' => [
                'articulos' => $g->stock(),
            ],
            default => [
                'resumen'  => $g->resumen(),
                'por_dia'  => $g->porDia(),
                'familias' => $g->porFamilia(),
                'medios'   => $g->porMedioPago(),
            ],
        };

        // Un profesional sin permiso general solo ve su propia línea
        if ($soloPropios && isset($datos['profesionales'])) {
            $datos['profesionales'] = array_values(array_filter(
                $datos['profesionales'],
                fn ($p) => $p['etiqueta'] === $usuario->nombre
            ));
        }

        return $datos;
    }

    /**
     * Exportación a CSV.
     *
     * Con BOM y punto y coma: sin eso, Excel en español abre el fichero
     * en una sola columna y se come los acentos.
     */
    public function exportar(Request $peticion)
    {
        [$desde, $hasta] = $this->rango($peticion);

        $que = $peticion->string('que', 'por_dia')->toString();
        $g   = GeneradorInformes::entre($desde, $hasta);

        [$cabeceras, $filas] = match ($que) {
            'familias'     => [['Familia', 'Unidades', 'Total'],
                               array_map(fn ($f) => [$f['etiqueta'], $f['unidades'], $f['total']], $g->porFamilia())],
            'articulos'    => [['Artículo', 'Unidades', 'Total'],
                               array_map(fn ($a) => [$a['etiqueta'], $a['unidades'], $a['total']], $g->porArticulo(500))],
            'profesionales'=> [['Profesional', 'Tickets', 'Total', 'Comisión'],
                               array_map(fn ($p) => [$p['etiqueta'], $p['tickets'], $p['total'], $p['comision']], $g->porProfesional())],
            'medios'       => [['Medio', 'Veces', 'Total'],
                               array_map(fn ($m) => [$m['etiqueta'], $m['veces'], $m['total']], $g->porMedioPago())],
            'inactivos'    => [['Cliente', 'Teléfono', 'Email', 'Última visita', 'Meses', 'Visitas'],
                               array_map(fn ($c) => [$c['etiqueta'], $c['telefono'], $c['email'], $c['ultima'], $c['meses'], $c['visitas']], $g->clientesInactivos(6, 1000))],
            'mejores'      => [['Cliente', 'Teléfono', 'Visitas', 'Total', 'Gasto medio'],
                               array_map(fn ($c) => [$c['etiqueta'], $c['telefono'], $c['visitas'], $c['total'], $c['medio']], $g->mejoresClientes(500))],
            'libro'        => [['Documento', 'Fecha', 'Estado', 'Base', 'Impuesto', 'Total'],
                               array_map(fn ($l) => [$l['documento'], $l['fecha'], $l['estado'], $l['base'], $l['impuesto'], $l['total']], $g->libroFacturas())],
            'invitaciones' => [['Documento', 'Fecha', 'Artículo', 'Motivo', 'Usuario', 'Importe'],
                               array_map(fn ($i) => [$i['documento'], $i['fecha'], $i['etiqueta'], $i['motivo'], $i['usuario'], $i['total']], $g->invitaciones())],
            'stock'        => [['Artículo', 'Familia', 'Stock', 'Mínimo', 'Valor'],
                               array_map(fn ($s) => [$s['etiqueta'], $s['familia'], $s['stock'], $s['minimo'], $s['valor']], $g->stock())],
            default        => [['Fecha', 'Tickets', 'Total'],
                               array_map(fn ($d) => [$d['fecha']->format('d/m/Y'), $d['tickets'], $d['total']], $g->porDia())],
        };

        $nombre = 'climacopos_' . $que . '_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd') . '.csv';

        return new StreamedResponse(function () use ($cabeceras, $filas) {
            $salida = fopen('php://output', 'w');

            // BOM UTF-8: sin esto Excel destroza los acentos
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, $cabeceras, ';');

            foreach ($filas as $fila) {
                // Coma decimal: es lo que espera Excel en español
                $fila = array_map(
                    fn ($v) => is_float($v) ? number_format($v, 2, ',', '') : $v,
                    $fila
                );

                fputcsv($salida, $fila, ';');
            }

            fclose($salida);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    /** Rangos rápidos y fechas a medida. */
    protected function rango(Request $peticion): array
    {
        $rango = $peticion->string('rango', 'mes')->toString();

        [$desde, $hasta] = match ($rango) {
            'hoy'          => [now()->startOfDay(), now()->endOfDay()],
            'ayer'         => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'semana'       => [now()->startOfWeek(), now()->endOfWeek()],
            'semana_pasada'=> [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'mes_pasado'   => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'trimestre'    => [now()->startOfQuarter(), now()->endOfQuarter()],
            'ano'          => [now()->startOfYear(), now()->endOfYear()],
            'medida'       => [
                Carbon::parse($peticion->input('desde', now()->startOfMonth()->toDateString()))->startOfDay(),
                Carbon::parse($peticion->input('hasta', now()->toDateString()))->endOfDay(),
            ],
            default        => [now()->startOfMonth(), now()->endOfMonth()],
        };

        return [$desde, $hasta, $rango];
    }
}
