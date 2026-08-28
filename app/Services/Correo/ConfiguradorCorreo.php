<?php

namespace App\Services\Correo;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Aplica la configuración de correo antes de cada envío.
 *
 * El SMTP no está en el .env sino en la base de datos, y puede ser
 * distinto para cada salón. Laravel lee la configuración al arrancar,
 * así que hay que sobreescribirla en caliente justo antes de enviar.
 *
 * DOS NIVELES
 *
 *   Plataforma  ── SMTP por defecto, el que usa todo el mundo.
 *                  Se configura en admin.climacopos.com.
 *
 *   Empresa     ── SMTP propio, opcional. Un salón con dominio y correo
 *                  propios preferirá que los emails salgan desde su
 *                  dirección y no desde la nuestra: llegan menos a spam
 *                  y dan mejor imagen.
 */
class ConfiguradorCorreo
{
    /**
     * Deja la configuración lista para enviar en nombre de la empresa
     * actual. Devuelve el remitente que se va a usar.
     *
     * @return array{email: string, nombre: string}
     */
    public function preparar(): array
    {
        $empresa = tenancy()->initialized ? tenant() : null;

        // ¿El salón tiene SMTP propio?
        if ($empresa && $empresa->correo_propio && filled($empresa->correo_host)) {
            return $this->aplicar([
                'host'       => $empresa->correo_host,
                'puerto'     => (int) $empresa->correo_puerto,
                'usuario'    => $empresa->correo_usuario,
                'password'   => $this->descifrar($empresa->correo_password),
                'cifrado'    => $empresa->correo_cifrado,
                'remitente'  => $empresa->correo_remitente ?: $empresa->email,
                'nombre'     => $empresa->nombre_comercial,
            ]);
        }

        // SMTP de la plataforma
        $host = config_plataforma('correo_host');

        if (blank($host)) {
            throw new RuntimeException(
                'No hay servidor de correo configurado. '
                . 'Configúralo en Administración → Correo.'
            );
        }

        return $this->aplicar([
            'host'      => $host,
            'puerto'    => (int) config_plataforma('correo_puerto', 587),
            'usuario'   => config_plataforma('correo_usuario'),
            'password'  => config_plataforma('correo_password'),
            'cifrado'   => config_plataforma('correo_cifrado', 'tls'),
            'remitente' => config_plataforma('correo_remitente', 'no-responder@climacopos.com'),

            /**
             * El nombre del remitente es el del SALÓN aunque el servidor
             * sea nuestro. Una clienta que reserva en «Peluquería Jectán»
             * espera un correo de la peluquería, no de una marca que no
             * conoce.
             */
            'nombre'    => $empresa?->nombre_comercial
                           ?? config_plataforma('correo_nombre', 'CLIMACO POS'),
        ]);
    }

    protected function aplicar(array $datos): array
    {
        /**
         * En pruebas NO se cambia el transporte.
         *
         * El .env.testing usa MAIL_MAILER=array, que guarda los mensajes en
         * memoria en lugar de enviarlos. Si aqui se forzara 'smtp', una
         * bateria de pruebas acabaria mandando correos de verdad a
         * direcciones de ejemplo, o colgandose intentando conectar.
         *
         * La configuracion del servidor si se aplica, para poder
         * comprobarla; lo unico que no se toca es por donde sale.
         */
        $enPruebas = app()->environment('testing') || config('mail.default') === 'array';

        if (! $enPruebas) {
            Config::set('mail.default', 'smtp');
        }

        Config::set('mail.mailers.smtp', [
            'transport'  => 'smtp',
            'host'       => $datos['host'],
            'port'       => $datos['puerto'] ?: 587,
            'username'   => $datos['usuario'] ?: null,
            'password'   => $datos['password'] ?: null,
            'encryption' => $datos['cifrado'] === 'ninguno' ? null : $datos['cifrado'],
            'timeout'    => 15,

            /**
             * Sin esto, un servidor con certificado autofirmado —habitual
             * en hostings compartidos— rechaza la conexión sin explicar
             * por qué. Solo se relaja si el administrador lo pide.
             */
            'verify_peer' => (bool) config_plataforma('correo_verificar_certificado', true),
        ]);

        Config::set('mail.from', [
            'address' => $datos['remitente'],
            'name'    => $datos['nombre'],
        ]);

        return ['email' => $datos['remitente'], 'nombre' => $datos['nombre']];
    }

    protected function descifrar(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /** ¿Se puede enviar correo ahora mismo? */
    public function disponible(): bool
    {
        $empresa = tenancy()->initialized ? tenant() : null;

        if ($empresa && $empresa->correo_propio && filled($empresa->correo_host)) {
            return true;
        }

        return filled(config_plataforma('correo_host'));
    }
}
