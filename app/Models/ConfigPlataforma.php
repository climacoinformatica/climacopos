<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Illuminate\Support\Facades\Crypt;

/**
 * Ajustes de la plataforma, guardados en la base central.
 *
 * Sustituye a las variables de entorno para todo lo que un administrador
 * deba poder cambiar sin abrir un fichero por SSH. Las claves secretas
 * se cifran con la APP_KEY.
 */
class ConfigPlataforma extends Model
{
    /**
     * Este modelo vive en la base CENTRAL.
     *
     * Dentro del contexto de una empresa, la conexion por defecto de
     * Eloquent es la del salon, asi que sin esto la consulta buscaria la
     * tabla en climacopos_emp_N y no existe alli. El trait CentralConnection
     * de stancl/tenancy fuerza la conexion central siempre.
     */
    use CentralConnection;

    protected $table = 'configuracion_plataforma';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** Claves que se guardan cifradas. */
    public const SECRETAS = [
        'stripe_secreto',
        'stripe_webhook',
        'redsys_clave',
        'correo_password',
    ];

    /**
     * Lee un ajuste, descifrando si hace falta.
     * Se cachea en memoria durante la petición.
     */
    public static function obtener(string $clave, $porDefecto = null)
    {
        static $cache = [];

        if (array_key_exists($clave, $cache)) {
            return $cache[$clave] ?? $porDefecto;
        }

        $fila = static::find($clave);

        if (! $fila || $fila->valor === null || $fila->valor === '') {
            return $cache[$clave] = $porDefecto;
        }

        $valor = $fila->valor;

        if ($fila->cifrado) {
            try {
                $valor = Crypt::decryptString($valor);
            } catch (\Throwable) {
                // Si cambió la APP_KEY, el valor ya no se puede leer
                return $cache[$clave] = $porDefecto;
            }
        }

        return $cache[$clave] = match (true) {
            $valor === 'true'  => true,
            $valor === 'false' => false,
            is_numeric($valor) => $valor + 0,
            default            => $valor,
        };
    }

    public static function guardar(string $clave, $valor): void
    {
        $cifrar = in_array($clave, self::SECRETAS, true);

        if (is_bool($valor)) {
            $valor = $valor ? 'true' : 'false';
        }

        $valor = (string) $valor;

        static::updateOrCreate(['clave' => $clave], [
            'valor'   => $valor === '' ? null : ($cifrar ? Crypt::encryptString($valor) : $valor),
            'cifrado' => $cifrar && $valor !== '',
        ]);
    }

    /** ¿Hay algo guardado en esta clave? Sin revelar el valor. */
    public static function tiene(string $clave): bool
    {
        return filled(static::find($clave)?->valor);
    }

    /** Enmascara un secreto para mostrarlo sin exponerlo. */
    public static function enmascarar(?string $valor): string
    {
        if (blank($valor)) {
            return '';
        }

        return strlen($valor) > 12
            ? substr($valor, 0, 7) . str_repeat('•', 12) . substr($valor, -4)
            : str_repeat('•', strlen($valor));
    }
}
