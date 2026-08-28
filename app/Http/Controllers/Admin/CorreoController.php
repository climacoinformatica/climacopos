<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigPlataforma;
use App\Services\Correo\GestorCorreos;
use Illuminate\Http\Request;

class CorreoController extends Controller
{
    /** Presets de los proveedores más habituales. */
    public const PROVEEDORES = [
        'gmail'    => ['nombre' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com',
                       'puerto' => 587, 'cifrado' => 'tls',
                       'nota' => 'Necesitas una «contraseña de aplicación», no la de tu cuenta.'],
        'outlook'  => ['nombre' => 'Outlook / Microsoft 365', 'host' => 'smtp.office365.com',
                       'puerto' => 587, 'cifrado' => 'tls',
                       'nota' => 'Requiere tener activado el envío SMTP en el panel de Microsoft.'],
        'ionos'    => ['nombre' => 'IONOS', 'host' => 'smtp.ionos.es',
                       'puerto' => 587, 'cifrado' => 'tls', 'nota' => null],
        'hostinger'=> ['nombre' => 'Hostinger', 'host' => 'smtp.hostinger.com',
                       'puerto' => 587, 'cifrado' => 'tls', 'nota' => null],
        'brevo'    => ['nombre' => 'Brevo (antes Sendinblue)', 'host' => 'smtp-relay.brevo.com',
                       'puerto' => 587, 'cifrado' => 'tls',
                       'nota' => 'Recomendado si envías mucho volumen: 300 correos al día gratis.'],
        'otro'     => ['nombre' => 'Otro servidor', 'host' => '', 'puerto' => 587,
                       'cifrado' => 'tls', 'nota' => null],
    ];

    public function index()
    {
        return view('admin.ajustes-correo', [
            'host'        => ConfigPlataforma::obtener('correo_host', ''),
            'puerto'      => ConfigPlataforma::obtener('correo_puerto', 587),
            'usuario'     => ConfigPlataforma::obtener('correo_usuario', ''),
            'tienePass'   => ConfigPlataforma::tiene('correo_password'),
            'cifrado'     => ConfigPlataforma::obtener('correo_cifrado', 'tls'),
            'remitente'   => ConfigPlataforma::obtener('correo_remitente', ''),
            'nombre'      => ConfigPlataforma::obtener('correo_nombre', 'CLIMACO POS'),
            'verificar'   => ConfigPlataforma::obtener('correo_verificar_certificado', true),
            'proveedores' => self::PROVEEDORES,
        ]);
    }

    public function guardar(Request $peticion)
    {
        $datos = $peticion->validate([
            'correo_host'      => ['required', 'string', 'max:120'],
            'correo_puerto'    => ['required', 'integer', 'min:1', 'max:65535'],
            'correo_usuario'   => ['nullable', 'string', 'max:160'],
            'correo_password'  => ['nullable', 'string', 'max:200'],
            'correo_cifrado'   => ['required', 'in:tls,ssl,ninguno'],
            'correo_remitente' => ['required', 'email', 'max:160'],
            'correo_nombre'    => ['required', 'string', 'max:100'],
        ]);

        foreach ($datos as $clave => $valor) {
            // La contraseña vacía no borra la guardada
            if ($clave === 'correo_password' && blank($valor)) {
                continue;
            }

            ConfigPlataforma::guardar($clave, $valor);
        }

        ConfigPlataforma::guardar('correo_verificar_certificado',
            $peticion->boolean('correo_verificar_certificado'));

        return back()->with('exito', 'Configuración guardada. Prueba el envío antes de darlo por bueno.');
    }

    public function probar(Request $peticion)
    {
        $peticion->validate(['destino' => ['required', 'email']]);

        $resultado = (new GestorCorreos())->prueba($peticion->input('destino'));

        return back()->with($resultado['ok'] ? 'exito' : 'error', $resultado['mensaje']);
    }
}
