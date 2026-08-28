<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Support\Intervalo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GestorReservas
{
    public function __construct(
        protected MotorHuecos $motor = new MotorHuecos(),
    ) {
    }

    /**
     * Crea una cita con uno o varios servicios encadenados.
     *
     * @param array $servicios [['articulo_id' => 1, 'usuario_id' => 2], ...]
     */
    public function crear(
        Carbon $fecha,
        string $hora,
        array $servicios,
        array $datosCliente,
        string $origen = 'LOCAL',
        ?Usuario $creadaPor = null,
        bool $comprobarDisponibilidad = true,
    ): Reserva {
        if ($servicios === []) {
            throw new RuntimeException('La cita necesita al menos un servicio.');
        }

        return DB::transaction(function () use (
            $fecha, $hora, $servicios, $datosCliente, $origen, $creadaPor, $comprobarDisponibilidad
        ) {
            $cliente = $this->resolverCliente($datosCliente, $origen);

            $estado = $origen === 'ONLINE'
                ? (config_empresa('confirmacion_automatica', false) ? 'CONFIRMADA' : 'PENDIENTE')
                : 'CONFIRMADA';

            $reserva = Reserva::create([
                'cliente_id'       => $cliente?->id,
                'cliente_nombre'   => $datosCliente['nombre'] ?? $cliente?->nombreCompleto() ?? 'Sin nombre',
                'cliente_telefono' => $datosCliente['telefono'] ?? $cliente?->telefono,
                'cliente_email'    => $datosCliente['email'] ?? $cliente?->email,
                'fecha'            => $fecha->toDateString(),
                'hora_ini'         => $hora,
                'hora_fin'         => $hora,      // se recalcula al final
                'estado'           => $estado,
                'origen'           => $origen,
                'notas_cliente'    => $datosCliente['notas'] ?? null,
                'creada_por'       => $creadaPor?->id,
            ]);

            $minuto = Intervalo::aMinutos($hora);
            $orden  = 0;

            foreach ($servicios as $entrada) {
                $articulo = Articulo::findOrFail($entrada['articulo_id']);
                $profesional = isset($entrada['usuario_id'])
                    ? Usuario::find($entrada['usuario_id'])
                    : null;

                // "Cualquiera": se elige el primero que esté libre
                $profesional ??= $this->primerProfesionalLibre($fecha, Intervalo::aHora($minuto), $articulo);

                if (! $profesional) {
                    throw new RuntimeException(
                        "No hay ningún profesional libre para «{$articulo->nombre}» a las "
                        . Intervalo::aHora($minuto) . '.'
                    );
                }

                if ($comprobarDisponibilidad
                    && ! $this->motor->estaLibre($fecha, Intervalo::aHora($minuto), $articulo, $profesional)) {
                    throw new RuntimeException(
                        "{$profesional->nombre} no está libre a las " . Intervalo::aHora($minuto)
                        . " para «{$articulo->nombre}»."
                    );
                }

                $reserva->lineas()->create([
                    'articulo_id'      => $articulo->id,
                    'usuario_id'       => $profesional->id,
                    'recurso_id'       => $articulo->recurso_id,
                    'orden'            => ++$orden,
                    'hora_ini'         => Intervalo::aHora($minuto),
                    'duracion_min'     => $articulo->duracionPara($profesional),
                    'tiempo_pausa_min' => $articulo->tiempo_pausa_min,
                    'tiempo_final_min' => $articulo->tiempo_final_min,
                    'precio'           => $articulo->precioPara($profesional),
                    'nombre_servicio'  => $articulo->nombre,
                ]);

                // El siguiente servicio empieza cuando acaba este,
                // pausa incluida (la clienta sigue en el salón)
                $minuto += $articulo->duracionPara($profesional)
                         + $articulo->tiempo_pausa_min
                         + $articulo->tiempo_final_min;
            }

            $reserva->recalcular();

            /**
         * Aviso al cliente.
         *
         * Si la reserva nace CONFIRMADA (confirmacion automatica), se manda
         * la confirmacion; si nace PENDIENTE, un acuse de recibo. Recibir
         * «cita confirmada» cuando todavia no lo esta genera plantones y
         * discusiones en el mostrador.
         */
        if (tenant('avisar_reserva') && filled($reserva->cliente_email)) {
            $correos = new \App\Services\Correo\GestorCorreos();

            $reserva->estado === 'CONFIRMADA'
                ? $correos->reservaConfirmada($reserva)
                : $correos->reservaPendiente($reserva);
        }

        Auditoria::registrar('reserva_creada', 'reservas', $reserva->id, [
                'codigo' => $reserva->codigo,
                'fecha'  => $reserva->fecha->format('d/m/Y'),
                'hora'   => $hora,
                'origen' => $origen,
            ], $creadaPor?->id);

            return $reserva->fresh('lineas');
        });
    }

    /** Mueve una cita a otro hueco y/o profesional. */
    public function mover(Reserva $reserva, Carbon $fecha, string $hora, ?Usuario $profesional = null): Reserva
    {
        if (! $reserva->estaAbierta()) {
            throw new RuntimeException('Esta cita ya está cerrada y no se puede mover.');
        }

        return DB::transaction(function () use ($reserva, $fecha, $hora, $profesional) {
            $minuto = Intervalo::aMinutos($hora);

            foreach ($reserva->lineas as $linea) {
                $destinatario = $profesional ?? $linea->usuario;
                $articulo     = $linea->articulo;

                if ($articulo && $destinatario && ! $this->motor->estaLibre(
                    $fecha, Intervalo::aHora($minuto), $articulo, $destinatario, $reserva->id
                )) {
                    throw new RuntimeException(
                        'El hueco de las ' . Intervalo::aHora($minuto) . ' no está libre.'
                    );
                }

                $linea->update([
                    'hora_ini'   => Intervalo::aHora($minuto),
                    'usuario_id' => $destinatario?->id ?? $linea->usuario_id,
                ]);

                $minuto += $linea->duracionTotal();
            }

            $reserva->update(['fecha' => $fecha->toDateString()]);
            $reserva->recalcular();

            Auditoria::registrar('reserva_movida', 'reservas', $reserva->id, [
                'codigo' => $reserva->codigo,
                'nueva'  => $fecha->format('d/m/Y') . ' ' . $hora,
            ]);

            return $reserva->fresh('lineas');
        });
    }

    protected function primerProfesionalLibre(Carbon $fecha, string $hora, Articulo $articulo): ?Usuario
    {
        foreach ($this->motor->profesionalesDe($articulo) as $profesional) {
            if ($this->motor->estaLibre($fecha, $hora, $articulo, $profesional)) {
                return $profesional;
            }
        }

        return null;
    }

    /**
     * Busca al cliente por teléfono; si no existe, lo crea.
     * El teléfono es el identificador real en un salón.
     */
    protected function resolverCliente(array $datos, string $origen): ?Cliente
    {
        if (! empty($datos['cliente_id'])) {
            return Cliente::find($datos['cliente_id']);
        }

        if (blank($datos['telefono'] ?? null) && blank($datos['nombre'] ?? null)) {
            return null;   // cita sin ficha, p. ej. un hueco reservado a boli
        }

        if ($existente = Cliente::porTelefono($datos['telefono'] ?? null)) {
            return $existente;
        }

        return Cliente::create([
            'nombre'               => $datos['nombre'] ?? 'Cliente',
            'apellidos'            => $datos['apellidos'] ?? null,
            'telefono'             => $datos['telefono'] ?? null,
            'email'                => $datos['email'] ?? null,
            'origen'               => $origen === 'ONLINE' ? 'ONLINE' : 'MANUAL',
            'acepta_rgpd'          => (bool) ($datos['acepta_rgpd'] ?? false),
            'acepta_marketing'     => (bool) ($datos['acepta_marketing'] ?? false),
            'fecha_consentimiento' => ($datos['acepta_rgpd'] ?? false) ? now() : null,
            'ip_consentimiento'    => ($datos['acepta_rgpd'] ?? false) ? request()->ip() : null,
        ]);
    }
}
