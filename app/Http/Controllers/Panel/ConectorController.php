<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Terminal;
use App\Support\Permisos;
use App\Support\SesionSalon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga del conector de impresión.
 *
 * CÓMO EVITA QUE EL CLIENTE CONFIGURE NADA
 *
 * Existe UN solo ejecutable compilado, igual para todos. Al descargarlo,
 * aquí se le añade al final un bloque con la dirección del salón y su
 * token. El programa lo lee de sí mismo al arrancar.
 *
 * Así el cliente hace doble clic, elige impresora y ya está: nunca ve un
 * token ni escribe una dirección. Y nosotros mantenemos un único binario
 * en lugar de recompilar por cada salón.
 *
 * EL TOKEN NO ES OPCIONAL
 *
 * La dirección del salón es pública: está en su web y en los correos a
 * las clientas. Sin token, cualquiera que la conozca podría pedir los
 * trabajos de impresión y recibir los tickets, con nombres, teléfonos e
 * importes. Es un dato personal y la responsabilidad sería nuestra.
 */
class ConectorController extends Controller
{
    /**
     * Dónde vive el ejecutable compilado.
     *
     * OJO CON storage_path() DENTRO DE UN SALÓN
     *
     * stancl/tenancy redirige `storage_path()` a la carpeta del tenant en
     * cuanto hay un salón activo: `storage/empresa5/app/...`. Tiene todo
     * el sentido, porque cada salón guarda sus fotos y sus certificados
     * aparte.
     *
     * Pero el conector es UNO SOLO para todos, así que se apunta a la
     * ruta base a mano. Si no, cada salón buscaría su propia copia del
     * ejecutable, y habría que duplicar seis megas por cliente.
     *
     * El síntoma era desconcertante: por consola el fichero se
     * encontraba —ahí no hay tenant— y por web no.
     */
    protected function rutaBinario(): string
    {
        return base_path('storage/app/conector/climaco-conector.exe');
    }

    public function descargar(Terminal $terminal)
    {
        abort_unless(
            SesionSalon::usuario()->tienePermiso(Permisos::AJUSTES_HARDWARE),
            403,
        );

        $binario = $this->rutaBinario();

        if (! is_file($binario)) {
            return back()->with('error',
                'El conector todavía no está disponible para descargar. '
                . 'Escríbenos y lo resolvemos.');
        }

        /**
         * Token nuevo en cada descarga.
         *
         * Si alguien se lleva el ejecutable de un equipo, basta con volver
         * a descargarlo desde el panel para que el anterior deje de valer.
         * Es la forma más simple de revocar sin explicar nada al cliente.
         */
        $token = Str::random(64);

        /**
         * En la base se guarda HASHEADO, nunca en claro.
         *
         * Asi un volcado de la base de datos no permite suplantar a un
         * agente y ponerse a recibir los tickets de un salon. El
         * middleware compara el hash, no el token.
         *
         * El token en claro solo existe en este instante, el tiempo justo
         * de incrustarlo en el ejecutable que se descarga.
         */
        $terminal->forceFill([
            'agente_token' => hash('sha256', $token),
        ])->save();

        Auditoria::registrar('conector_descargado', 'terminales', $terminal->id, [
            'terminal' => $terminal->nombre,
        ]);

        $datos = json_encode([
            'url'   => $this->urlDelSalon(),
            'token' => $token,
            'salon' => tenant('nombre_comercial'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $marca = "\n---CLIMACO-CONFIG---\n";

        $nombre = 'CLIMACO-Conector-' . Str::slug($terminal->nombre) . '.exe';

        return new StreamedResponse(function () use ($binario, $marca, $datos) {
            /**
             * Se envía por trozos, no de golpe.
             *
             * El ejecutable ronda los pocos megas, y leerlo entero en
             * memoria por cada descarga simultánea es gastar RAM sin
             * necesidad en un servidor compartido por muchos salones.
             */
            $entrada = fopen($binario, 'rb');

            while (! feof($entrada)) {
                echo fread($entrada, 8192);
                flush();
            }

            fclose($entrada);

            echo $marca;
            echo $datos;
        }, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Dirección pública de este salón.
     *
     * Se saca del dominio registrado y no de APP_URL, que apunta al
     * dominio central: el agente tiene que hablar con el subdominio del
     * salón, no con climacopos.com.
     */
    protected function urlDelSalon(): string
    {
        $dominio = tenant()->domains()->first()?->domain;

        if ($dominio) {
            return 'https://' . $dominio;
        }

        return rtrim(config('app.url'), '/');
    }
}
