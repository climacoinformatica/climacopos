<?php

namespace App\Services\Correo;

use App\Models\CorreoEnviado;
use App\Models\PagoOnline;
use App\Models\Reserva;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío de los correos del salón a sus clientes.
 *
 * Todos los envíos quedan registrados. No es burocracia: sirve para
 * responder a «no me ha llegado nada» sin adivinar, y para no mandar
 * dos veces el mismo recordatorio.
 */
class GestorCorreos
{
    public function __construct(
        protected ConfiguradorCorreo $configurador = new ConfiguradorCorreo(),
    ) {
    }

    // ------------------------------------------------------------------
    // Reservas
    // ------------------------------------------------------------------

    public function reservaConfirmada(Reserva $reserva): bool
    {
        return $this->enviar(
            $reserva->cliente_email,
            'RESERVA_CONFIRMADA',
            $reserva->id,
            'Tu cita en ' . tenant('nombre_comercial'),
            'correo.reserva-confirmada',
            ['reserva' => $reserva, 'empresa' => tenant()],
        );
    }

    public function reservaPendiente(Reserva $reserva): bool
    {
        return $this->enviar(
            $reserva->cliente_email,
            'RESERVA_PENDIENTE',
            $reserva->id,
            'Hemos recibido tu solicitud de cita',
            'correo.reserva-pendiente',
            ['reserva' => $reserva, 'empresa' => tenant()],
        );
    }

    public function reservaCancelada(Reserva $reserva, ?string $motivo = null): bool
    {
        return $this->enviar(
            $reserva->cliente_email,
            'RESERVA_CANCELADA',
            $reserva->id,
            'Tu cita ha sido cancelada',
            'correo.reserva-cancelada',
            ['reserva' => $reserva, 'empresa' => tenant(), 'motivo' => $motivo],
        );
    }

    /**
     * Recordatorio de la víspera.
     *
     * Es el correo que más dinero ahorra: un plantón deja un hueco que ya
     * no se vende. Un recordatorio bien puesto reduce bastante los
     * plantones, y por eso lleva control estricto de duplicados: recibir
     * dos recordatorios de la misma cita resulta descuidado.
     */
    public function recordatorio(Reserva $reserva): bool
    {
        if ($this->yaEnviado('RECORDATORIO', $reserva->id)) {
            return false;
        }

        return $this->enviar(
            $reserva->cliente_email,
            'RECORDATORIO',
            $reserva->id,
            'Recordatorio: tu cita es mañana',
            'correo.recordatorio',
            ['reserva' => $reserva, 'empresa' => tenant()],
        );
    }

    public function pagoRecibido(PagoOnline $pago): bool
    {
        $reserva = $pago->reserva;

        return $this->enviar(
            $reserva?->cliente_email,
            'PAGO_RECIBIDO',
            $pago->id,
            'Hemos recibido tu pago',
            'correo.pago-recibido',
            ['pago' => $pago, 'reserva' => $reserva, 'empresa' => tenant()],
        );
    }

    public function devolucionHecha(PagoOnline $pago): bool
    {
        $reserva = $pago->reserva;

        return $this->enviar(
            $reserva?->cliente_email,
            'DEVOLUCION',
            $pago->id,
            'Te hemos devuelto el importe',
            'correo.devolucion',
            ['pago' => $pago, 'reserva' => $reserva, 'empresa' => tenant()],
        );
    }

    // ------------------------------------------------------------------

    /**
     * Envío genérico.
     *
     * Nunca lanza excepción: un fallo de correo no puede tumbar el cobro
     * de un ticket ni impedir que se cree una reserva. Se registra y se
     * sigue adelante.
     */
    protected function enviar(
        ?string $destino,
        string $tipo,
        ?int $referenciaId,
        string $asunto,
        string $plantilla,
        array $datos,
    ): bool {
        if (blank($destino) || ! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (! $this->configurador->disponible()) {
            $this->registrar($tipo, $destino, $referenciaId, $asunto, 'SIN_CONFIGURAR',
                'No hay servidor de correo configurado.');

            return false;
        }

        try {
            $remitente = $this->configurador->preparar();

            Mail::send($plantilla, $datos, function ($mensaje) use ($destino, $asunto, $remitente) {
                $mensaje->to($destino)
                        ->subject($asunto)
                        ->from($remitente['email'], $remitente['nombre']);

                // Las respuestas van al salón, no al buzón de la plataforma
                if ($correoSalon = tenant('email')) {
                    $mensaje->replyTo($correoSalon, tenant('nombre_comercial'));
                }
            });

            $this->registrar($tipo, $destino, $referenciaId, $asunto, 'ENVIADO');

            return true;
        } catch (\Throwable $e) {
            Log::warning('Fallo al enviar correo', [
                'empresa' => tenant()?->slug,
                'tipo'    => $tipo,
                'error'   => $e->getMessage(),
            ]);

            $this->registrar($tipo, $destino, $referenciaId, $asunto, 'ERROR',
                mb_substr($e->getMessage(), 0, 400));

            return false;
        }
    }

    protected function registrar(
        string $tipo,
        string $destino,
        ?int $referenciaId,
        string $asunto,
        string $estado,
        ?string $error = null,
    ): void {
        try {
            CorreoEnviado::create([
                'tipo'          => $tipo,
                'destinatario'  => $destino,
                'referencia_id' => $referenciaId,
                'asunto'        => $asunto,
                'estado'        => $estado,
                'error'         => $error,
                'enviado_en'    => now(),
            ]);
        } catch (\Throwable) {
            // Si ni el registro se puede escribir, no vale la pena insistir
        }
    }

    protected function yaEnviado(string $tipo, int $referenciaId): bool
    {
        return CorreoEnviado::where('tipo', $tipo)
            ->where('referencia_id', $referenciaId)
            ->where('estado', 'ENVIADO')
            ->exists();
    }

    /** Correo de prueba, para comprobar la configuración. */
    public function prueba(string $destino): array
    {
        try {
            $remitente = $this->configurador->preparar();

            Mail::raw(
                "Esto es una prueba de CLIMACO POS.\n\n"
                . "Si lo estás leyendo, el correo saliente funciona correctamente.\n\n"
                . 'Enviado el ' . now()->format('d/m/Y \a \l\a\s H:i') . '.',
                function ($mensaje) use ($destino, $remitente) {
                    $mensaje->to($destino)
                            ->subject('Prueba de correo · CLIMACO POS')
                            ->from($remitente['email'], $remitente['nombre']);
                },
            );

            return ['ok' => true, 'mensaje' => "Correo enviado a {$destino}. Revisa también la carpeta de spam."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $this->traducirError($e->getMessage())];
        }
    }

    /**
     * Los errores de SMTP son crípticos. Se traducen a algo accionable:
     * quien configura esto no tiene por qué saber qué es un handshake TLS.
     */
    protected function traducirError(string $error): string
    {
        return match (true) {
            str_contains($error, 'Authentication')       => 'Usuario o contraseña incorrectos.',
            str_contains($error, 'Connection could not') => 'No se pudo conectar. Comprueba el servidor y el puerto.',
            str_contains($error, 'certificate')          => 'Problema con el certificado del servidor. '
                                                            . 'Prueba a desactivar la verificación de certificado.',
            str_contains($error, 'timed out')            => 'El servidor no responde. '
                                                            . 'Puede que el puerto esté bloqueado por el cortafuegos.',
            str_contains($error, 'Relay')                => 'El servidor no permite enviar desde esa dirección. '
                                                            . 'El remitente debe coincidir con la cuenta.',
            default                                      => 'Error: ' . mb_substr($error, 0, 200),
        };
    }
}
