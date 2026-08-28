<?php

namespace App\Services\Correo;

use App\Models\Cuenta;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correos de la PLATAFORMA a los salones.
 *
 * No confundir con GestorCorreos, que manda avisos del salón a sus
 * clientas. Aquí el remitente somos nosotros y el destinatario es el
 * dueño de la peluquería: impagos, suspensión y borrado.
 *
 * Estos correos van siempre por el SMTP de la plataforma, nunca por el
 * del salón: un aviso de impago enviado desde el propio servidor del
 * moroso es una mala idea por razones evidentes.
 */
class CorreosPlataforma
{
    public function __construct(
        protected ConfiguradorCorreo $configurador = new ConfiguradorCorreo(),
    ) {
    }

    /**
     * Primer impago: solo aviso, nada se corta todavía.
     * El tono importa: casi siempre es una tarjeta caducada.
     */
    public function primerImpago(Empresa $empresa): bool
    {
        return $this->enviar($empresa,
            'No hemos podido cobrar tu cuota de CLIMACO POS',
            'correo.plataforma.impago-primero',
        );
    }

    /** Segundo impago: la cuenta pasa a solo lectura esta madrugada. */
    public function suspensionInminente(Empresa $empresa): bool
    {
        return $this->enviar($empresa,
            'Importante: tu cuenta pasará a solo lectura',
            'correo.plataforma.suspension',
        );
    }

    /** Aviso de borrado, quince días antes. */
    public function avisoBorrado(Empresa $empresa): bool
    {
        return $this->enviar($empresa,
            'Tus datos se eliminarán el ' . $empresa->borrar_a_partir_de?->format('d/m/Y'),
            'correo.plataforma.borrado',
        );
    }

    /** La prueba termina en unos días. */
    public function pruebaTermina(Empresa $empresa, int $dias): bool
    {
        return $this->enviar($empresa,
            $dias <= 1
                ? 'Tu prueba de CLIMACO POS termina mañana'
                : "Tu prueba de CLIMACO POS termina en {$dias} días",
            'correo.plataforma.prueba-termina',
            ['dias' => $dias],
        );
    }

    // ------------------------------------------------------------------
    //  Correos a una CUENTA, no a una empresa
    // ------------------------------------------------------------------

    /**
     * Correo de bienvenida con el enlace de verificacion.
     *
     * Sin este metodo el registro no servia de nada: la cuenta se creaba
     * pero nadie recibia el enlace, y quedaba bloqueada sin poder entrar.
     * Es lo que nos obligo a verificar cuentas a mano por base de datos.
     */
    public function verificarCuenta(Cuenta $cuenta): bool
    {
        return $this->enviarACuenta($cuenta,
            'Confirma tu cuenta de CLIMACO POS',
            'correo.plataforma.verificar',
            ['enlace' => route('web.verificar', ['token' => $cuenta->token_verificacion])],
        );
    }

    /**
     * Enlace para volver a entrar cuando alguien olvida su contrasena.
     *
     * Sin esto, un cliente que pierde la contrasena pierde el acceso a su
     * salon para siempre: no hay otra via de entrada.
     */
    public function recuperarAcceso(Cuenta $cuenta, string $enlace): bool
    {
        return $this->enviarACuenta($cuenta,
            'Recupera el acceso a CLIMACO POS',
            'correo.plataforma.recuperar',
            ['enlace' => $enlace],
        );
    }

    /**
     * Aviso de que la contrasena acaba de cambiar.
     *
     * Se manda SIEMPRE, aunque el cambio sea legitimo: si alguien ha
     * entrado en la cuenta sin permiso, este correo es lo unico que
     * avisara al dueno de que algo pasa.
     */
    public function contrasenaCambiada(Cuenta $cuenta): bool
    {
        return $this->enviarACuenta($cuenta,
            'Tu contrasena de CLIMACO POS ha cambiado',
            'correo.plataforma.contrasena-cambiada',
        );
    }

    protected function enviarACuenta(Cuenta $cuenta, string $asunto, string $plantilla, array $extra = []): bool
    {
        if (blank($cuenta->email) || ! filter_var($cuenta->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        /**
         * Fuera del contexto del salon, igual que los demas.
         *
         * Estos correos son de la plataforma, no de ningun salon: tienen
         * que salir de nuestro SMTP aunque quien los pida este dentro de
         * su panel.
         */
        $estabaDentro = tenancy()->initialized;
        $empresaActual = $estabaDentro ? tenant() : null;

        if ($estabaDentro) {
            tenancy()->end();
        }

        try {
            if (! $this->configurador->disponible()) {
                Log::warning('Sin SMTP configurado: no se envio el correo a la cuenta', [
                    'cuenta' => $cuenta->email,
                    'asunto' => $asunto,
                ]);

                return false;
            }

            $remitente = $this->configurador->preparar();

            Mail::send($plantilla, array_merge(['cuenta' => $cuenta], $extra),
                function ($mensaje) use ($cuenta, $asunto, $remitente) {
                    $mensaje->to($cuenta->email, $cuenta->nombre)
                            ->subject($asunto)
                            ->from($remitente['email'], 'CLIMACO POS');
                });

            Log::info('Correo de plataforma enviado a cuenta', [
                'cuenta' => $cuenta->email,
                'asunto' => $asunto,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Fallo al enviar correo a la cuenta', [
                'cuenta' => $cuenta->email,
                'error'  => $e->getMessage(),
            ]);

            return false;
        } finally {
            if ($estabaDentro && $empresaActual) {
                tenancy()->initialize($empresaActual);
            }
        }
    }

    // ------------------------------------------------------------------

    protected function enviar(Empresa $empresa, string $asunto, string $plantilla, array $extra = []): bool
    {
        if (blank($empresa->email) || ! filter_var($empresa->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        /**
         * Se sale del contexto del salón antes de preparar el correo.
         *
         * Si no, ConfiguradorCorreo cogería su SMTP propio si lo tuviera,
         * y el aviso de impago saldría desde el servidor del moroso.
         */
        $estabaDentro = tenancy()->initialized;
        $empresaActual = $estabaDentro ? tenant() : null;

        if ($estabaDentro) {
            tenancy()->end();
        }

        try {
            if (! $this->configurador->disponible()) {
                return false;
            }

            $remitente = $this->configurador->preparar();

            Mail::send($plantilla, array_merge(['empresa' => $empresa], $extra),
                function ($mensaje) use ($empresa, $asunto, $remitente) {
                    $mensaje->to($empresa->email, $empresa->nombre_comercial)
                            ->subject($asunto)
                            ->from($remitente['email'], 'CLIMACO POS');
                });

            Log::info('Correo de plataforma enviado', [
                'empresa' => $empresa->slug,
                'asunto'  => $asunto,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Fallo al enviar correo de plataforma', [
                'empresa' => $empresa->slug,
                'error'   => $e->getMessage(),
            ]);

            return false;
        } finally {
            if ($estabaDentro && $empresaActual) {
                tenancy()->initialize($empresaActual);
            }
        }
    }
}
