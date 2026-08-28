<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\ColaImpresion;
use App\Models\DisenoTicket;
use App\Models\Terminal;
use App\Services\GestorImagenes;
use App\Services\GestorImpresion;
use App\Support\SesionSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

 class HardwareController extends Controller
{
    public function __construct(
        protected GestorImpresion $impresion = new GestorImpresion(),
    ) {
    }

    // ------------------------------------------------------------------
    // Terminales y hardware
    // ------------------------------------------------------------------

    public function index()
    {
        return view('panel.ajustes.hardware', [
            'terminales' => Terminal::with('config')->orderBy('nombre')->get(),
            'actual'     => SesionSalon::terminal(),
            'cola'       => ColaImpresion::with('terminal')->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function guardarTerminal(Request $peticion, Terminal $terminal)
    {
        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:60'],

            'impresora_tickets_modo'   => ['required', 'in:RED,LOCAL'],
            'impresora_tickets_ip'     => ['nullable', 'string', 'max:60'],
            'impresora_tickets_puerto' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'impresora_tickets_local'  => ['nullable', 'string', 'max:120'],
            'impresora_ancho_mm'       => ['required', 'in:58,80'],

            'impresora_etiquetas_ip'     => ['nullable', 'string', 'max:60'],
            'impresora_etiquetas_puerto' => ['nullable', 'integer', 'min:1', 'max:65535'],

            'cajon_modo'   => ['required', 'in:IMPRESORA,SERIE,NINGUNO'],
            'cajon_pin'    => ['required', 'in:2,5'],
            'cajon_puerto' => ['nullable', 'string', 'max:20'],

            'visor_puerto'  => ['nullable', 'string', 'max:20'],
            'visor_baudios' => ['nullable', 'integer'],
            'visor_linea1_reposo' => ['nullable', 'string', 'max:20'],
            'visor_linea2_reposo' => ['nullable', 'string', 'max:20'],

            'agente_intervalo_ms' => ['nullable', 'integer', 'min:500', 'max:10000'],

            'teclado_tactil' => ['nullable', 'in:auto,siempre,nunca'],
	    'ticket_imprimir' => ['nullable', 'in:SIEMPRE,PREGUNTAR,NUNCA'],
        ]);

        $terminal->update(['nombre' => $datos['nombre']]);
        unset($datos['nombre']);

        foreach ($datos as $clave => $valor) {
            $terminal->fijarAjuste($clave, $valor ?? '');
        }

        Auditoria::registrar('hardware_configurado', 'terminales', $terminal->id, [
            'nombre' => $terminal->nombre,
        ]);

        return back()->with('exito', "Configuración de «{$terminal->nombre}» guardada.");
    }

    /**
     * Genera el token del agente y lo muestra UNA vez.
     * Se guarda hasheado, así que si se pierde hay que generar otro.
     */
    public function tokenAgente(Terminal $terminal)
    {
        $token = Str::random(48);

        $terminal->forceFill([
            'agente_token' => hash('sha256', $token),
        ])->save();

        Auditoria::registrar('agente_token_generado', 'terminales', $terminal->id);

        return back()->with('token_agente', [
            'terminal' => $terminal->nombre,
            'token'    => $token,
            'url'      => tenant()->urlPortal(),
        ]);
    }

    // ------------------------------------------------------------------
    // Pruebas de hardware
    // ------------------------------------------------------------------

    public function probar(Request $peticion, Terminal $terminal)
    {
        $peticion->validate(['que' => ['required', 'in:TICKET,CAJON,VISOR']]);

        match ($peticion->string('que')->toString()) {
            'TICKET' => $this->impresion->prueba($terminal),
            'CAJON'  => $this->impresion->abrirCajon($terminal),
            'VISOR'  => $this->impresion->visor('PRUEBA DE VISOR', now()->format('d/m/Y H:i')),
        };

        return back()->with('exito',
            'Trabajo enviado a la cola. Si el agente está funcionando, debería verse en unos segundos.');
    }

    public function reintentar(ColaImpresion $trabajo)
    {
        $trabajo->forceFill(['estado' => 'PENDIENTE', 'intentos' => 0, 'error' => null])->save();

        return back()->with('exito', 'Trabajo devuelto a la cola.');
    }

    public function purgarCola()
    {
        $cuantos = ColaImpresion::purgar(0);

        return back()->with('exito', "Se han eliminado {$cuantos} trabajo(s) completados.");
    }

    // ------------------------------------------------------------------
    // Diseñador de ticket
    // ------------------------------------------------------------------

    public function diseno()
    {
        return view('panel.ajustes.ticket-diseno', [
            'diseno' => DisenoTicket::activo(),
        ]);
    }

    public function guardarDiseno(Request $peticion, GestorImagenes $imagenes)
    {
        $diseno = DisenoTicket::activo();

        $datos = $peticion->validate([
            'nombre'          => ['required', 'string', 'max:60'],
            'ancho_mm'        => ['required', 'in:58,80'],
            'logo'            => ['nullable', 'image', 'max:4096'],
            'logo_alineacion' => ['required', 'in:IZQUIERDA,CENTRO,DERECHA'],
            'logo_ancho_px'   => ['required', 'integer', 'min:64', 'max:576'],

            'cabecera'                => ['nullable', 'array', 'max:8'],
            'cabecera.*.texto'        => ['nullable', 'string', 'max:60'],
            'cabecera.*.alineacion'   => ['nullable', 'in:IZQUIERDA,CENTRO,DERECHA'],

            'pie'                => ['nullable', 'array', 'max:8'],
            'pie.*.texto'        => ['nullable', 'string', 'max:60'],
            'pie.*.alineacion'   => ['nullable', 'in:IZQUIERDA,CENTRO,DERECHA'],

            'texto_legal'    => ['nullable', 'string', 'max:500'],
            'lineas_finales' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $atributos = [
            'nombre'          => $datos['nombre'],
            'ancho_mm'        => (int) $datos['ancho_mm'],
            'columnas'        => \App\Services\EscPos::columnasPara((int) $datos['ancho_mm']),
            'logo_alineacion' => $datos['logo_alineacion'],
            'logo_ancho_px'   => (int) $datos['logo_ancho_px'],
            'texto_legal'     => $datos['texto_legal'] ?? null,
            'lineas_finales'  => (int) $datos['lineas_finales'],

            'cabecera' => $this->limpiarFilas($peticion->input('cabecera', [])),
            'pie'      => $this->limpiarFilas($peticion->input('pie', [])),

            'mostrar_qr_verifactu'      => $peticion->boolean('mostrar_qr_verifactu'),
            'mostrar_qr_reserva'        => $peticion->boolean('mostrar_qr_reserva'),
            'mostrar_cliente'           => $peticion->boolean('mostrar_cliente'),
            'mostrar_profesional'       => $peticion->boolean('mostrar_profesional'),
            'mostrar_desglose_impuesto' => $peticion->boolean('mostrar_desglose_impuesto'),
            'cortar_papel'              => $peticion->boolean('cortar_papel'),
            'abrir_cajon_efectivo'      => $peticion->boolean('abrir_cajon_efectivo'),
        ];

        if ($peticion->hasFile('logo')) {
            $atributos['logo'] = $imagenes->subirImagenSimple($peticion->file('logo'), 'ticket');
        }

        $diseno->update($atributos);

        Auditoria::registrar('ticket_diseno_guardado', 'ticket_disenos', $diseno->id);

        return back()->with('exito', 'Diseño del ticket guardado.');
    }

    protected function limpiarFilas(array $filas): array
    {
        $salida = [];

        foreach ($filas as $fila) {
            if (blank($fila['texto'] ?? null)) {
                continue;
            }

            $salida[] = [
                'texto'       => $fila['texto'],
                'alineacion'  => $fila['alineacion'] ?? 'CENTRO',
                'negrita'     => ! empty($fila['negrita']),
                'doble_alto'  => ! empty($fila['doble_alto']),
                'doble_ancho' => ! empty($fila['doble_ancho']),
            ];
        }

        return $salida;
    }

    /**
     * Ajustes que son del SALON, no de un terminal concreto.
     *
     * Vive en la pantalla de Hardware por comodidad, pero se guarda en
     * `empresas`: si hubiera dos terminales no tendria sentido que se
     * comportaran distinto al cobrar.
     */
    public function guardarSalon(Request $peticion)
    {
        $datos = $peticion->validate([
            'tras_cobrar' => ['required', 'in:NADA,SELECTOR,INICIO'],
        ]);

        tenant()->update($datos);

        Auditoria::registrar('ajuste_salon', 'empresas', tenant('id'), $datos);

        return back()->with('exito', 'Ajuste guardado.');
    }
}
