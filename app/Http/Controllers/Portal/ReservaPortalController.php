<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\Aviso;
use App\Models\Familia;
use App\Models\Reserva;
use App\Models\ReservaTemporal;
use App\Models\Usuario;
use App\Services\GestorReservas;
use App\Services\MotorHuecos;
use App\Support\Intervalo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Portal público de reservas: {slug}.climacopos.com
 *
 * Cuatro pasos, todos renderizados en servidor. Sin framework de front:
 * un portal de reservas tiene que funcionar en el móvil viejo de una
 * clienta con mala cobertura, y cada kilobyte de JavaScript es una
 * reserva menos.
 */
class ReservaPortalController extends Controller
{
    public function __construct(
        protected MotorHuecos $motor = new MotorHuecos(),
        protected GestorReservas $gestor = new GestorReservas(),
    ) {
    }

    /** Paso 1: elegir servicio */
    public function inicio()
    {
        $familias = Familia::visiblesOnline()
            ->deServicios()
            ->with(['articulos' => fn ($q) => $q->reservablesOnline()->with('fotos')->orderBy('orden')])
            ->orderBy('orden')
            ->get()
            ->filter(fn ($f) => $f->articulos->isNotEmpty());

        return view('portal.servicios', [
            'familias' => $familias,
            'empresa'  => tenant(),
        ]);
    }

    /** Paso 2: profesional, día y hora */
    public function elegirHueco(Request $peticion, Articulo $articulo)
    {
        abort_unless($articulo->permite_reserva_online && $articulo->activo, 404);

        // Las retenciones muertas no deben esconder huecos libres
        ReservaTemporal::purgarCaducadas();

        $fecha = $peticion->filled('fecha')
            ? Carbon::parse($peticion->string('fecha')->toString())
            : Carbon::today();

        $maxDias = (int) config_empresa('antelacion_max_dias', 60);
        $limite  = Carbon::today()->addDays($maxDias);

        if ($fecha->isBefore(Carbon::today())) {
            $fecha = Carbon::today();
        }

        if ($fecha->isAfter($limite)) {
            $fecha = $limite;
        }

        $profesional = $peticion->filled('usuario_id')
            ? Usuario::activos()->profesionales()->find($peticion->integer('usuario_id'))
            : null;

        $mapa = $this->motor->huecosConProfesional($fecha, $articulo, $profesional);

        $sugerido = $mapa === []
            ? $this->motor->primerDiaConHueco($fecha, $articulo, $profesional, $maxDias)
            : null;

        return view('portal.hueco', [
            'articulo'      => $articulo,
            'fecha'         => $fecha,
            'profesional'   => $profesional,
            'profesionales' => $this->motor->profesionalesDe($articulo),
            'huecos'        => $mapa,
            'sugerido'      => $sugerido,
            'limite'        => $limite,
            'empresa'       => tenant(),
        ]);
    }

    /** Paso 3: datos del cliente */
    public function datos(Request $peticion, Articulo $articulo)
    {
        $datos = $peticion->validate([
            'fecha'      => ['required', 'date'],
            'hora'       => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
        ]);

        $fecha = Carbon::parse($datos['fecha']);

        ReservaTemporal::purgarCaducadas();

        $profesional = ! empty($datos['usuario_id'])
            ? Usuario::find($datos['usuario_id'])
            : $this->primerLibre($fecha, $datos['hora'], $articulo);

        if (! $profesional) {
            return redirect()
                ->route('portal.hueco', ['articulo' => $articulo, 'fecha' => $fecha->toDateString()])
                ->with('error', 'Ese hueco acaba de ocuparse. Elige otro, por favor.');
        }

        $horaFin = $this->horaFin($datos['hora'], $articulo->duracionTotal($profesional));

        /**
         * CORRECCION IMPORTANTE
         *
         * Antes se creaba una retencion nueva en cada visita a este paso.
         * Si el cliente volvia atras y repetia, se acumulaban varias para
         * el mismo hueco, y al confirmar solo se borraba la del token
         * actual: las otras seguian bloqueando y la reserva se rechazaba
         * a si misma con «ese hueco acaba de ocuparse».
         *
         * Ahora se limpian todas las del mismo hueco antes de crear la
         * nueva. Solo puede haber una retencion viva por hueco.
         */
        $this->limpiarRetenciones($profesional->id, $fecha, $datos['hora'], $horaFin);

        $retencion = ReservaTemporal::create([
            'usuario_id' => $profesional->id,
            'fecha'      => $fecha->toDateString(),
            'hora_ini'   => $datos['hora'],
            'hora_fin'   => $horaFin,
        ]);

        return view('portal.datos', [
            'articulo'    => $articulo,
            'fecha'       => $fecha,
            'hora'        => $datos['hora'],
            'profesional' => $profesional,
            'retencion'   => $retencion,
            'empresa'     => tenant(),
        ]);
    }

    /** Paso 4: crear la reserva */
    public function confirmar(Request $peticion, Articulo $articulo)
    {
        $datos = $peticion->validate([
            'fecha'       => ['required', 'date'],
            'hora'        => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'usuario_id'  => ['required', 'exists:usuarios,id'],
            'token'       => ['nullable', 'string'],
            'nombre'      => ['required', 'string', 'max:80'],
            'apellidos'   => ['nullable', 'string', 'max:120'],
            'telefono'    => ['required', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:160'],
            'notas'       => ['nullable', 'string', 'max:500'],
            'acepta_rgpd' => ['accepted'],
            'marketing'   => ['nullable'],
        ], [
            'acepta_rgpd.accepted' => 'Necesitamos tu consentimiento para guardar tus datos.',
            'telefono.required'    => 'El teléfono es necesario para poder avisarte de cualquier cambio.',
        ]);

        $fecha       = Carbon::parse($datos['fecha']);
        $profesional = Usuario::find($datos['usuario_id']);
        $horaFin     = $this->horaFin($datos['hora'], $articulo->duracionTotal($profesional));

        /**
         * Se quitan TODAS las retenciones de ese hueco, no solo la del
         * token: la reserva real va a ocuparlo de todas formas, y una
         * retencion olvidada haria que la reserva chocara consigo misma.
         */
        $this->limpiarRetenciones($datos['usuario_id'], $fecha, $datos['hora'], $horaFin);

        try {
            $reserva = $this->gestor->crear(
                $fecha,
                $datos['hora'],
                [['articulo_id' => $articulo->id, 'usuario_id' => $datos['usuario_id']]],
                [
                    'nombre'           => $datos['nombre'],
                    'apellidos'        => $datos['apellidos'] ?? null,
                    'telefono'         => $datos['telefono'],
                    'email'            => $datos['email'] ?? null,
                    'notas'            => $datos['notas'] ?? null,
                    'acepta_rgpd'      => true,
                    'acepta_marketing' => (bool) ($datos['marketing'] ?? false),
                ],
                origen: 'ONLINE',
            );
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('portal.hueco', ['articulo' => $articulo, 'fecha' => $datos['fecha']])
                ->with('error', 'Ese hueco se ha ocupado mientras rellenabas tus datos. Elige otro, por favor.');
        }

        return redirect()->route('portal.reserva', $reserva->codigo);
    }

    /** Consulta de la reserva por su código */
    public function verReserva(string $codigo)
    {
        $reserva = Reserva::with(['lineas.usuario'])->where('codigo', $codigo)->firstOrFail();

        return view('portal.reserva', [
            'reserva'       => $reserva,
            'empresa'       => tenant(),
            'puedeCancelar' => $this->puedeCancelar($reserva),
        ]);
    }

    public function cancelarReserva(Request $peticion, string $codigo)
    {
        $reserva = Reserva::where('codigo', $codigo)->firstOrFail();

        if (! $this->puedeCancelar($reserva)) {
            return back()->with('error',
                'Ya no es posible cancelar por internet. Llámanos y lo arreglamos.');
        }

        $reserva->cancelar('CLIENTE');
        Aviso::reservaCancelada($reserva);
        Aviso::resolverDeReserva($reserva->id);

        return redirect()->route('portal.reserva', $codigo)
            ->with('exito', 'Tu cita ha quedado cancelada.');
    }

    // ------------------------------------------------------------------

    /**
     * Borra las retenciones de un profesional que pisan un tramo horario.
     * Se usa antes de crear una nueva (para no duplicar) y antes de
     * confirmar la reserva definitiva.
     */
    protected function limpiarRetenciones(int $usuarioId, Carbon $fecha, string $horaIni, string $horaFin): void
    {
        $nuevo = Intervalo::desdeHoras($horaIni, $horaFin);

        $retenciones = ReservaTemporal::where('usuario_id', $usuarioId)
            ->whereDate('fecha', $fecha->toDateString())
            ->get();

        foreach ($retenciones as $retencion) {
            $existente = Intervalo::desdeHoras($retencion->hora_ini, $retencion->hora_fin);

            if ($existente->solapaCon($nuevo)) {
                $retencion->delete();
            }
        }
    }

    protected function puedeCancelar(Reserva $reserva): bool
    {
        if (! $reserva->estaAbierta()) {
            return false;
        }

        $horas = (int) config_empresa('cancelacion_horas_min', 24);

        $momento = Carbon::parse($reserva->fecha->toDateString() . ' ' . $reserva->hora_ini);

        return $momento->isAfter(now()->addHours($horas));
    }

    protected function primerLibre(Carbon $fecha, string $hora, Articulo $articulo): ?Usuario
    {
        foreach ($this->motor->profesionalesDe($articulo) as $profesional) {
            if ($this->motor->estaLibre($fecha, $hora, $articulo, $profesional)) {
                return $profesional;
            }
        }

        return null;
    }

    protected function horaFin(string $hora, int $minutos): string
    {
        return Intervalo::aHora(Intervalo::aMinutos($hora) + $minutos);
    }
}
