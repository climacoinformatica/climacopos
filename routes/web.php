<?php

use App\Http\Controllers\Admin\AccesoController;
use App\Http\Controllers\Admin\AjustesController;
use App\Http\Controllers\Admin\CorreoController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\PlanesController;
use App\Http\Controllers\Web\AltaController;
use App\Http\Controllers\Web\AreaController;
use App\Http\Controllers\Web\PaginaController;
use App\Http\Controllers\Web\RegistroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
|  DOMINIO CENTRAL
|--------------------------------------------------------------------------
|
|  climacopos.com          web comercial y area de clientes
|  admin.climacopos.com    panel de superadministracion
|
|  POR QUE NO HAY UN BUCLE SOBRE central_domains
|
|  La version anterior recorria los tres dominios centrales declarando las
|  mismas rutas en cada uno. Parecia razonable, pero rompia route():
|  Laravel guarda UN solo registro por nombre, asi que `web.inicio` acababa
|  apuntando al ultimo dominio del bucle. Y cuando tenant.php se cargaba
|  despues y tocaba la coleccion de rutas, la referencia se perdia del todo:
|  la ruta existia y hacia match, pero `route('web.inicio')` dentro de una
|  vista lanzaba «Route not defined».
|
|  Aqui cada nombre se declara UNA vez. El `www` se resuelve en nginx con
|  una redireccion, que es donde corresponde y ademas evita contenido
|  duplicado de cara a los buscadores.
|
|  POR QUE LA RAIZ LLEVA DOMINIO Y EL RESTO NO
|
|  routes/tenant.php declara tambien `Route::get('/')` para el portal de
|  cada salon. Sin dominio, las dos competirian. Con dominio, la central
|  solo responde en climacopos.com y en los subdominios sigue ganando la
|  del portal, que es la correcta alli.
|
|  Las demas rutas (/contacto, /crear-cuenta...) no chocan con ninguna del
|  portal, asi que no necesitan dominio.
|
*/

// ---------------------------------------------------------------- Raiz

Route::domain(env('APP_DOMAIN', 'climacopos.com'))
    ->get('/', [PaginaController::class, 'inicio'])
    ->name('web.inicio');

// ---------------------------------------------------------------- Publico

Route::get('soluciones/{slug}', [PaginaController::class, 'producto'])->name('web.producto');
Route::get('contacto', [PaginaController::class, 'contacto'])->name('web.contacto');
Route::get('legal/{documento}', [PaginaController::class, 'legal'])->name('web.legal');

// ---------------------------------------------------------------- Registro

Route::middleware('guest:cuenta')->group(function () {
    Route::get('crear-cuenta', [RegistroController::class, 'formulario'])->name('web.registro');
    Route::post('crear-cuenta', [RegistroController::class, 'registrar'])->name('web.registro.enviar');

    Route::get('entrar', [RegistroController::class, 'acceso'])->name('web.acceso');
    Route::post('entrar', [RegistroController::class, 'entrar'])->name('web.acceso.entrar');
});


/*
|--------------------------------------------------------------------------
|  Recuperar el acceso
|--------------------------------------------------------------------------
|
|  Sin esto, un cliente que olvida su contrasena pierde el acceso a su
|  salon para siempre: no hay otra via de entrada.
|
*/
Route::get('olvidada', [RegistroController::class, 'olvidada'])->name('web.olvidada');
Route::post('olvidada', [RegistroController::class, 'enviarEnlace'])->name('web.olvidada.enviar');
Route::get('restablecer/{token}', [RegistroController::class, 'restablecer'])->name('web.restablecer');
Route::post('restablecer', [RegistroController::class, 'guardarNueva'])->name('web.restablecer.guardar');

Route::get('cuenta-creada', [RegistroController::class, 'enviado'])->name('web.registro.enviado');
Route::post('reenviar-verificacion', [RegistroController::class, 'reenviar'])->name('web.registro.reenviar');

/**
 * Enlace del correo de verificacion.
 *
 * Sin firmar y con token largo: el correo tiene que funcionar aunque se
 * abra dias despues y desde otro dispositivo. Un enlace que caduca en una
 * hora genera mas soporte del que evita.
 */
Route::get('verificar/{token}', [RegistroController::class, 'verificar'])->name('web.verificar');

Route::post('salir', [RegistroController::class, 'salir'])->name('web.salir');

// ---------------------------------------------------------------- Area

Route::middleware('auth:cuenta')->prefix('mi-cuenta')->group(function () {
    Route::get('/', [AreaController::class, 'inicio'])->name('web.area');

    Route::get('descargas', [AreaController::class, 'descargas'])->name('web.area.descargas');
    Route::get('descargar/{version}', [AreaController::class, 'descargar'])->name('web.area.descargar');

    Route::get('datos', [AreaController::class, 'perfil'])->name('web.area.perfil');
    Route::post('datos', [AreaController::class, 'guardarPerfil'])->name('web.area.perfil.guardar');
    Route::post('contrasena', [RegistroController::class, 'cambiarPassword'])->name('web.area.contrasena');

    // ---- Alta de salon
    Route::get('crear-salon', [AltaController::class, 'formulario'])->name('web.alta');
    Route::post('crear-salon', [AltaController::class, 'crear'])->name('web.alta.crear');
    Route::get('salon-listo', [AltaController::class, 'listo'])->name('web.alta.listo');
    Route::get('comprobar-direccion', [AltaController::class, 'comprobar'])->name('web.alta.comprobar');
});

// ---------------------------------------------------------------- Webhook

/**
 * Avisos de Stripe sobre las SUSCRIPCIONES de los salones.
 *
 * Stripe llama aqui cuando un salon paga su cuota, cuando falla el cobro
 * o cuando cancela. Sin esto, Stripe cobraria pero el sistema no se
 * enteraria: la suscripcion nunca se marcaria como activa.
 *
 * NO CONFUNDIR con el webhook de routes/tenant.php, que es para los
 * cobros del salon a sus clientas.
 *
 * Va sin CSRF ni sesion: la peticion viene de los servidores de Stripe,
 * no de un navegador. Lo que la autentica es la firma HMAC, que el
 * controlador comprueba antes de tocar nada.
 */
Route::domain(config('tenancy.central_domains')[0] ?? 'climacopos.com')
    ->post('webhook/billing', \App\Http\Controllers\Webhook\BillingWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.billing');

// ---------------------------------------------------------------- Admin

/**
 * Panel de superadministracion, en su propio subdominio.
 *
 * Va con dominio explicito porque su raiz `/` tambien competiria con la
 * del portal de los salones.
 */
$dominioAdmin = collect(config('tenancy.central_domains'))
    ->first(fn ($d) => str_starts_with($d, 'admin.')) ?? 'admin.climacopos.com';

Route::domain($dominioAdmin)->name('admin.')->group(function () {

    Route::get('acceso', [AccesoController::class, 'mostrar'])->name('acceso');
    Route::post('acceso', [AccesoController::class, 'entrar'])->name('acceso.entrar');

    Route::middleware('superadmin')->group(function () {
        Route::post('salir', [AccesoController::class, 'salir'])->name('salir');

        Route::get('/', [PanelController::class, 'inicio'])->name('inicio');
        Route::get('empresa/{empresa}', [PanelController::class, 'empresa'])->name('empresa');
        Route::post('empresa/{empresa}/estado', [PanelController::class, 'cambiarEstado'])->name('empresa.estado');

        Route::get('correo', [CorreoController::class, 'index'])->name('correo');
        Route::post('correo', [CorreoController::class, 'guardar'])->name('correo.guardar');
        Route::post('correo/probar', [CorreoController::class, 'probar'])->name('correo.probar');

        /**
         * Planes de suscripcion.
         *
         * Hasta ahora solo se podian crear por base de datos, asi que en
         * la practica no habia ninguno: el formulario de alta no ofrecia
         * nada que contratar.
         */
        Route::get('planes', [PlanesController::class, 'index'])->name('planes');
        Route::post('planes', [PlanesController::class, 'crear'])->name('planes.crear');
        Route::post('planes/sincronizar', [PlanesController::class, 'sincronizar'])->name('planes.sincronizar');
        Route::post('planes/{plan}', [PlanesController::class, 'guardar'])->name('planes.guardar');
        Route::post('planes/{plan}/sincronizar', [PlanesController::class, 'sincronizar'])->name('planes.sincronizar.uno');
        Route::delete('planes/{plan}', [PlanesController::class, 'borrar'])->name('planes.borrar');

        Route::get('ajustes/pagos', [AjustesController::class, 'pagos'])->name('ajustes.pagos');
        Route::post('ajustes/pagos', [AjustesController::class, 'guardarPagos'])->name('ajustes.pagos.guardar');
        Route::post('ajustes/pagos/probar', [AjustesController::class, 'probarPagos'])->name('ajustes.pagos.probar');
        Route::post('ajustes/pagos/borrar', [AjustesController::class, 'borrarClave'])->name('ajustes.pagos.borrar');
    });
});
