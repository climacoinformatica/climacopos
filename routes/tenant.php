<?php

use App\Http\Controllers\Panel\ConectorController;
use App\Http\Controllers\Panel\ProduccionController;
use App\Http\Controllers\Panel\LogotipoController;
use App\Http\Controllers\Panel\AgendaController;
use App\Http\Controllers\Panel\AjustesController;
use App\Http\Controllers\Panel\ArticuloController;
use App\Http\Controllers\Panel\AvisoController;
use App\Http\Controllers\Panel\CajaController;
use App\Http\Controllers\Agente\AgenteController;
use App\Http\Controllers\Panel\BonoController;
use App\Http\Controllers\Panel\AusenciaController;
use App\Http\Controllers\Panel\FestivoController;
use App\Http\Controllers\Panel\ClienteController;
use App\Http\Controllers\Panel\FichajeController;
use App\Http\Controllers\Panel\UsuarioController;
use App\Http\Controllers\Panel\DevolucionController;
use App\Http\Controllers\Panel\FamiliaController;
use App\Http\Controllers\Panel\HardwareController;
use App\Http\Controllers\Panel\InformesController;
use App\Http\Controllers\Panel\HorarioController;
use App\Http\Controllers\Panel\ReautenticacionController;
use App\Http\Controllers\Panel\SelectorController;
use App\Http\Controllers\Panel\SuscripcionController;
use App\Http\Controllers\Panel\VerifactuController;
use App\Http\Controllers\Panel\TerminalController;
use App\Http\Controllers\Panel\TpvController;
use App\Http\Controllers\Panel\PagosController;
use App\Http\Controllers\Portal\PagoController;
use App\Http\Controllers\Portal\ReservaPortalController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------
| WEBHOOK DE STRIPE
|--------------------------------------------------------------------
| Sin sesion ni CSRF: se valida con la firma HMAC de Stripe. Es la
| fuente de verdad del cobro, porque la vuelta del navegador puede no
| llegar nunca si el cliente cierra la pestana.
*/
Route::post('webhook/stripe', StripeWebhookController::class)
    ->middleware([
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
    ])
    ->name('webhook.stripe');

/*
|--------------------------------------------------------------------
| API DEL AGENTE
|--------------------------------------------------------------------
| Sin sesion ni CSRF: se autentica por token. Va antes del grupo web
| para que no le apliquen las cookies del panel.
*/
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'agente',
])->prefix('agente')->name('agente.')->group(function () {
    Route::get('saludo', [AgenteController::class, 'saludo'])->name('saludo');
    Route::get('trabajos', [AgenteController::class, 'trabajos'])->name('trabajos');
    Route::post('trabajos/{trabajo}/confirmar', [AgenteController::class, 'confirmar'])->name('confirmar');
});

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    /*
    |----------------------------------------------------------------
    | PORTAL PÚBLICO
    |----------------------------------------------------------------
    */
    Route::get('/', [ReservaPortalController::class, 'inicio'])->name('portal.inicio');

    Route::get('reservar/{articulo}', [ReservaPortalController::class, 'elegirHueco'])->name('portal.hueco');
    Route::post('reservar/{articulo}/datos', [ReservaPortalController::class, 'datos'])->name('portal.datos');
    Route::post('reservar/{articulo}', [ReservaPortalController::class, 'confirmar'])->name('portal.confirmar');

    Route::get('cita/{codigo}/pagar', [PagoController::class, 'mostrar'])->name('portal.pago');
    Route::post('cita/{codigo}/pagar', [PagoController::class, 'iniciar'])->name('portal.pago.iniciar');
    Route::get('cita/{codigo}/pago-vuelta', [PagoController::class, 'vuelta'])->name('portal.pago.vuelta');

    Route::get('cita/{codigo}', [ReservaPortalController::class, 'verReserva'])->name('portal.reserva');
    Route::post('cita/{codigo}/cancelar', [ReservaPortalController::class, 'cancelarReserva'])->name('portal.cancelar');

    /*
    |----------------------------------------------------------------
    | PANEL
    |----------------------------------------------------------------
    */
    Route::prefix('panel')->name('panel.')->group(function () {
	
	Route::get('ajustes/reservas', [AjustesController::class, 'reservas'])->name('ajustes.reservas');
	
	Route::get('produccion', [ProduccionController::class, 'index'])->name('produccion');
	Route::get('produccion/parte', [ProduccionController::class, 'parte'])->name('produccion.parte');
	Route::get('produccion/detalle/{usuario}', [ProduccionController::class, 'detalle'])->name('produccion.detalle');
	Route::get('produccion/exportar', [ProduccionController::class, 'exportar'])->name('produccion.exportar');

        Route::get('vincular', [TerminalController::class, 'mostrarVinculacion'])->name('terminal.vincular');
        Route::post('vincular', [TerminalController::class, 'vincular'])->name('terminal.vincular.post');

        Route::middleware('terminal')->group(function () {

            Route::get('selector', [SelectorController::class, 'mostrar'])->name('selector');
            Route::post('selector', [SelectorController::class, 'entrar'])->name('selector.entrar');

            Route::middleware(['salon', 'suscripcion'])->group(function () {

                Route::post('salir', [SelectorController::class, 'salir'])->name('salir');

                // ---- Suscripción del salón
                Route::get('suscripcion', [SuscripcionController::class, 'index'])
                    ->middleware('permiso:empresa.facturacion')->name('suscripcion');
                Route::post('suscripcion/contratar', [SuscripcionController::class, 'contratar'])
                    ->middleware('permiso:empresa.facturacion')->name('suscripcion.contratar');
                Route::post('suscripcion/portal', [SuscripcionController::class, 'portal'])
                    ->middleware('permiso:empresa.facturacion')->name('suscripcion.portal');

                Route::get('reautenticar', [ReautenticacionController::class, 'mostrar'])->name('reautenticar');
                Route::post('reautenticar', [ReautenticacionController::class, 'confirmar'])->name('reautenticar.post');

                Route::get('/', fn () => view('panel.inicio'))->name('inicio');

                // ---- Avisos
                Route::get('avisos/contador', [AvisoController::class, 'contador'])->name('avisos.contador');
                Route::get('avisos/lista', [AvisoController::class, 'lista'])->name('avisos.lista');
                Route::post('avisos/{aviso}/leido', [AvisoController::class, 'marcarLeido'])->name('avisos.leido');
                Route::post('avisos/reserva/{reserva}', [AvisoController::class, 'resolverReserva'])->name('avisos.resolver');

                // ---- TPV
                Route::middleware('permiso:tpv.vender')->group(function () {
                    Route::get('tpv', [TpvController::class, 'index'])->name('tpv');
                    Route::get('tpv/nuevo', [TpvController::class, 'nuevo'])->name('tpv.nuevo');
                    Route::get('tpv/tickets', [TpvController::class, 'tickets'])->name('tpv.tickets');

                    Route::post('tpv/{ticket}/lineas', [TpvController::class, 'anadir'])->name('tpv.anadir');
                    Route::post('tpv/{ticket}/lineas/{linea}/cantidad', [TpvController::class, 'cantidad'])->name('tpv.cantidad');
                    Route::post('tpv/{ticket}/lineas/{linea}/quitar', [TpvController::class, 'quitarLinea'])->name('tpv.quitar');
                    Route::post('tpv/{ticket}/lineas/{linea}/descuento', [TpvController::class, 'descuento'])->name('tpv.descuento');
                    Route::post('tpv/{ticket}/lineas/{linea}/invitar', [TpvController::class, 'invitar'])->name('tpv.invitar');
                    Route::get('tpv/clientes', [TpvController::class, 'buscarClientes'])->name('tpv.clientes');
                    Route::post('tpv/{ticket}/cliente/nuevo', [TpvController::class, 'crearCliente'])->name('tpv.cliente.nuevo');
                    Route::get('tpv/{ticket}/lineas/{linea}/bonos', [TpvController::class, 'bonosDisponibles'])->name('tpv.bonos');
                    Route::post('tpv/{ticket}/lineas/{linea}/bono', [TpvController::class, 'usarBono'])->name('tpv.usar-bono');
                    Route::post('tpv/{ticket}/cliente', [TpvController::class, 'cliente'])->name('tpv.cliente');
                    Route::post('tpv/{ticket}/cobrar', [TpvController::class, 'cobrar'])->name('tpv.cobrar');
		    Route::post('tpv/{ticket}/imprimir', [TpvController::class, 'imprimir'])->name('tpv.imprimir');
                });

                Route::post('tpv/{ticket}/anular', [TpvController::class, 'anular'])
                    ->middleware('permiso:tpv.anular_ticket')->name('tpv.anular');

                // ---- Devoluciones (facturas rectificativas)
                Route::get('tpv/{ticket}/devolver', [DevolucionController::class, 'mostrar'])
                    ->middleware('permiso:tpv.anular_ticket')->name('tpv.devolucion');
                Route::post('tpv/{ticket}/devolver', [DevolucionController::class, 'devolver'])
                    ->middleware('permiso:tpv.anular_ticket')->name('tpv.devolver');

                // ---- Festivos y cierres
                Route::prefix('festivos')->name('festivos')
                    ->middleware('permiso:usuarios.gestionar,agenda.editar_otros')->group(function () {
                        Route::get('/', [FestivoController::class, 'index']);
                        Route::post('/', [FestivoController::class, 'guardar'])->name('.guardar');
                        Route::post('importar', [FestivoController::class, 'importar'])->name('.importar');
                        Route::delete('{festivo}', [FestivoController::class, 'borrar'])->name('.borrar');
                    });

                // ---- Ausencias y vacaciones
                // Solicitar es un derecho de cada persona: sin permiso especial.
                Route::get('ausencias', [AusenciaController::class, 'index'])->name('ausencias');
                Route::post('ausencias', [AusenciaController::class, 'solicitar'])->name('ausencias.solicitar');
                Route::post('ausencias/{ausencia}/cancelar', [AusenciaController::class, 'cancelar'])->name('ausencias.cancelar');

                Route::middleware('permiso:usuarios.gestionar')->group(function () {
                    Route::get('ausencias/calendario', [AusenciaController::class, 'calendario'])->name('ausencias.calendario');
                    Route::post('ausencias/{ausencia}/aprobar', [AusenciaController::class, 'aprobar'])->name('ausencias.aprobar');
                    Route::post('ausencias/{ausencia}/rechazar', [AusenciaController::class, 'rechazar'])->name('ausencias.rechazar');
                });

                // ---- Fichajes
                // Fichar es un derecho y un deber de cada persona: no
                // requiere permisos especiales. El informe si.
                Route::get('fichajes', [FichajeController::class, 'index'])->name('fichajes');
                Route::post('fichajes/fichar', [FichajeController::class, 'fichar'])->name('fichajes.fichar');

                // Cada persona accede a SU registro sin permisos especiales:
                // es un derecho reconocido por la normativa, no una funcion
                // administrativa que dependa de un responsable.
                Route::get('fichajes/mi-registro', [FichajeController::class, 'miRegistro'])->name('fichajes.mio');
                Route::get('fichajes/mi-registro/descargar', [FichajeController::class, 'miExportacion'])->name('fichajes.mio.exportar');

                Route::middleware('permiso:usuarios.gestionar')->group(function () {
                    Route::get('fichajes/informe', [FichajeController::class, 'informe'])->name('fichajes.informe');
                    Route::get('fichajes/exportar', [FichajeController::class, 'exportar'])->name('fichajes.exportar');
                    Route::post('fichajes/anadir', [FichajeController::class, 'anadir'])->name('fichajes.anadir');
                    Route::post('fichajes/{fichaje}/corregir', [FichajeController::class, 'corregir'])->name('fichajes.corregir');
                });

                // ---- Usuarios
                Route::prefix('usuarios')->name('usuarios')->middleware('permiso:usuarios.gestionar')->group(function () {
                    Route::get('/', [UsuarioController::class, 'index']);
                    Route::get('nuevo', [UsuarioController::class, 'crear'])->name('.crear');
                    Route::post('/', [UsuarioController::class, 'guardar'])->name('.guardar');
                    Route::get('{usuario}/editar', [UsuarioController::class, 'editar'])->name('.editar');
                    Route::post('{usuario}/editar', [UsuarioController::class, 'guardar'])->name('.guardar.editar');

                    Route::post('{usuario}/pin', [UsuarioController::class, 'nuevoPin'])->name('.pin');
                    Route::post('{usuario}/password', [UsuarioController::class, 'nuevaPassword'])->name('.password');
                    Route::post('{usuario}/desbloquear', [UsuarioController::class, 'desbloquear'])->name('.desbloquear');
                    Route::post('{usuario}/desactivar', [UsuarioController::class, 'desactivar'])->name('.desactivar');
                    Route::post('{usuario}/reactivar', [UsuarioController::class, 'reactivar'])
                        ->withTrashed()->name('.reactivar');
                });

                // ---- Clientes y fichas tecnicas
                Route::prefix('clientes')->name('clientes')->group(function () {
                    Route::get('/', [ClienteController::class, 'index']);
                    Route::get('nuevo', [ClienteController::class, 'crear'])->name('.crear');
                    Route::post('/', [ClienteController::class, 'guardar'])->name('.guardar');
                    Route::get('{cliente}', [ClienteController::class, 'ver'])->name('.ver');
                    Route::get('{cliente}/editar', [ClienteController::class, 'editar'])->name('.editar');
                    Route::post('{cliente}/editar', [ClienteController::class, 'guardar'])->name('.guardar.editar');

                    Route::get('{cliente}/ficha', [ClienteController::class, 'nuevaFicha'])->name('.ficha.nueva');
                    Route::post('{cliente}/ficha', [ClienteController::class, 'guardarFicha'])->name('.ficha.guardar');
                    Route::get('{cliente}/ficha/{ficha}', [ClienteController::class, 'editarFicha'])->name('.ficha.editar');
                    Route::post('{cliente}/ficha/{ficha}', [ClienteController::class, 'guardarFicha'])->name('.ficha.guardar.editar');
                    Route::delete('{cliente}/ficha/{ficha}', [ClienteController::class, 'borrarFicha'])->name('.ficha.borrar');

                    Route::post('{cliente}/recargar', [ClienteController::class, 'recargar'])->name('.recargar');
                });

                // ---- Bonos, monedero y vales
                Route::prefix('bonos')->name('bonos.')->group(function () {
                    Route::get('/', [BonoController::class, 'plantillas'])
                        ->middleware('permiso:catalogo.editar')->name('plantillas');
                    Route::get('nuevo', [BonoController::class, 'crear'])
                        ->middleware('permiso:catalogo.editar')->name('crear');
                    Route::post('/', [BonoController::class, 'guardar'])
                        ->middleware('permiso:catalogo.editar')->name('guardar');
                    Route::get('{plantilla}/editar', [BonoController::class, 'editar'])
                        ->middleware('permiso:catalogo.editar')->name('editar');
                    Route::post('{plantilla}/editar', [BonoController::class, 'guardar'])
                        ->middleware('permiso:catalogo.editar')->name('guardar.editar');
                    Route::delete('{plantilla}', [BonoController::class, 'borrar'])
                        ->middleware('permiso:catalogo.editar')->name('borrar');

                    Route::get('vendidos', [BonoController::class, 'vendidos'])->name('vendidos');
                    Route::post('emitir', [BonoController::class, 'emitir'])->name('emitir');
                    Route::get('ver/{bono}', [BonoController::class, 'ver'])->name('ver');
                    Route::post('ver/{bono}/anular', [BonoController::class, 'anular'])
                        ->middleware('permiso:catalogo.editar')->name('anular');

                    Route::get('vales', [BonoController::class, 'vales'])->name('vales');
                    Route::post('vales', [BonoController::class, 'emitirVale'])->name('vales.emitir');
                    Route::get('vales/consultar', [BonoController::class, 'consultarVale'])->name('vales.consultar');

                    Route::post('monedero/{cliente}', [BonoController::class, 'recargar'])->name('monedero');
                });

                // ---- Caja
                Route::get('caja', [CajaController::class, 'index'])
                    ->middleware('permiso:caja.cierre')->name('caja');
                Route::post('caja/movimiento', [CajaController::class, 'movimiento'])
                    ->middleware('permiso:caja.entradas_salidas')->name('caja.movimiento');
                Route::post('caja/cerrar', [CajaController::class, 'cerrar'])
                    ->middleware('permiso:caja.cierre')->name('caja.cerrar');
                Route::get('caja/cierre/{cierre}', [CajaController::class, 'verCierre'])
                    ->middleware('permiso:caja.cierre')->name('caja.cierre');

                // ---- Documentos de formación
                Route::get('caja/formacion', [CajaController::class, 'formacion'])
                    ->middleware('permiso:formacion.consultar')->name('caja.formacion');
                Route::get('caja/formacion/exportar', [CajaController::class, 'exportarFormacion'])
                    ->middleware('permiso:formacion.consultar')->name('caja.formacion.exportar');
                Route::post('caja/formacion/borrar', [CajaController::class, 'borrarFormacion'])
                    ->middleware('permiso:formacion.borrar')->name('caja.formacion.borrar');

                // ---- Agenda
                Route::get('agenda', [AgendaController::class, 'dia'])->name('agenda');
                Route::get('agenda/huecos', [AgendaController::class, 'huecos'])->name('agenda.huecos');
                Route::get('agenda/cita/nueva', [AgendaController::class, 'nuevaCita'])->name('agenda.cita.nueva');
                Route::post('agenda/cita', [AgendaController::class, 'guardarCita'])->name('agenda.cita.guardar');
                Route::get('agenda/cita/{reserva}', [AgendaController::class, 'verCita'])->name('agenda.cita');
                Route::post('agenda/cita/{reserva}/estado', [AgendaController::class, 'cambiarEstado'])
                    ->middleware('permiso:reservas.confirmar,agenda.editar_otros')->name('agenda.cita.estado');
                Route::post('agenda/cita/{reserva}/mover', [AgendaController::class, 'moverCita'])
                    ->middleware('permiso:agenda.editar_otros')->name('agenda.cita.mover');

                Route::post('agenda/bloqueos', [AgendaController::class, 'bloquear'])
                    ->middleware('permiso:agenda.editar_otros')->name('agenda.bloquear');
                Route::delete('agenda/bloqueos/{bloqueo}', [AgendaController::class, 'borrarBloqueo'])
                    ->middleware('permiso:agenda.editar_otros')->name('agenda.bloqueo.borrar');

                Route::get('agenda/clientes/buscar', [AgendaController::class, 'buscarClientes'])->name('clientes.buscar');

                // ---- Horarios
                Route::get('horarios', [HorarioController::class, 'index'])
                    ->middleware('permiso:usuarios.gestionar,agenda.editar_otros')->name('agenda.horarios');
                Route::post('horarios/{usuario}', [HorarioController::class, 'guardarHorario'])
                    ->middleware('permiso:usuarios.gestionar,agenda.editar_otros')->name('agenda.horarios.guardar');
                Route::post('excepciones', [HorarioController::class, 'guardarExcepcion'])
                    ->middleware('permiso:usuarios.gestionar,agenda.editar_otros')->name('agenda.excepciones.guardar');
                Route::delete('excepciones/{excepcion}', [HorarioController::class, 'borrarExcepcion'])
                    ->middleware('permiso:usuarios.gestionar,agenda.editar_otros')->name('agenda.excepciones.borrar');

                // ---- Catálogo
                Route::prefix('catalogo')->name('catalogo.')->middleware('permiso:catalogo.editar')->group(function () {
                    Route::get('familias', [FamiliaController::class, 'index'])->name('familias');
                    Route::get('familias/nueva', [FamiliaController::class, 'crear'])->name('familias.crear');
                    Route::post('familias', [FamiliaController::class, 'guardar'])->name('familias.guardar');
                    Route::get('familias/{familia}', [FamiliaController::class, 'editar'])->name('familias.editar');
                    Route::post('familias/{familia}', [FamiliaController::class, 'guardar'])->name('familias.guardar.editar');
                    Route::delete('familias/{familia}', [FamiliaController::class, 'borrar'])->name('familias.borrar');

                    Route::get('articulos', [ArticuloController::class, 'index'])->name('articulos');
                    Route::get('articulos/nuevo', [ArticuloController::class, 'crear'])->name('articulos.crear');
                    Route::post('articulos', [ArticuloController::class, 'guardar'])->name('articulos.guardar');
                    Route::get('articulos/{articulo}', [ArticuloController::class, 'editar'])->name('articulos.editar');
                    Route::post('articulos/{articulo}', [ArticuloController::class, 'guardar'])->name('articulos.guardar.editar');
                    Route::delete('articulos/{articulo}', [ArticuloController::class, 'borrar'])->name('articulos.borrar');

                    Route::post('fotos/{foto}/principal', [ArticuloController::class, 'fotoPrincipal'])->name('fotos.principal');
                    Route::post('fotos/{foto}/borrar', [ArticuloController::class, 'borrarFoto'])->name('fotos.borrar');

                });

                // ---- Ajustes
                	Route::get('ajustes', [AjustesController::class, 'index'])
                    ->middleware('permiso:ajustes.acceso')->name('ajustes');
                	Route::post('ajustes', [AjustesController::class, 'guardar'])
                    ->middleware('permiso:ajustes.acceso')->name('ajustes.guardar');
			Route::get('logotipo', [LogotipoController::class, 'ver'])->name('logotipo.ver');
			Route::post('logotipo', [LogotipoController::class, 'subir'])->name('logotipo.subir');
			Route::delete('logotipo', [LogotipoController::class, 'borrar'])->name('logotipo.borrar');
			Route::get('conector/{terminal}', [ConectorController::class, 'descargar'])->name('conector.descargar');

                // ---- Hardware y diseno del ticket
                Route::middleware('permiso:ajustes.hardware')->group(function () {
                    Route::get('ajustes/hardware', [HardwareController::class, 'index'])->name('ajustes.hardware');
			Route::post('ajustes/salon', [HardwareController::class, 'guardarSalon'])->name('ajustes.salon');
                    Route::post('ajustes/hardware/{terminal}', [HardwareController::class, 'guardarTerminal'])->name('ajustes.terminal');
                    Route::post('ajustes/hardware/{terminal}/token', [HardwareController::class, 'tokenAgente'])->name('ajustes.token');
                    Route::post('ajustes/hardware/{terminal}/probar', [HardwareController::class, 'probar'])->name('ajustes.probar');
                    Route::post('ajustes/cola/{trabajo}/reintentar', [HardwareController::class, 'reintentar'])->name('ajustes.reintentar');
                    Route::post('ajustes/cola/purgar', [HardwareController::class, 'purgarCola'])->name('ajustes.purgar');
                });

                Route::middleware('permiso:ajustes.ticket_diseno')->group(function () {
                    Route::get('ajustes/ticket', [HardwareController::class, 'diseno'])->name('ajustes.ticket');
                    Route::post('ajustes/ticket', [HardwareController::class, 'guardarDiseno'])->name('ajustes.ticket.guardar');
                });

                // ---- Pagos online
                Route::middleware('permiso:empresa.facturacion,ajustes.acceso')->group(function () {
                    Route::get('ajustes/pagos', [PagosController::class, 'index'])->name('ajustes.pagos');
                    Route::post('ajustes/pagos/conectar', [PagosController::class, 'conectar'])->name('ajustes.pagos.conectar');
                    Route::post('ajustes/pagos/comprobar', [PagosController::class, 'comprobar'])->name('ajustes.pagos.comprobar');
                    Route::post('ajustes/pagos/sincronizar', [PagosController::class, 'sincronizar'])->name('ajustes.pagos.sincronizar');
                    Route::post('ajustes/pagos/{pago}/devolver', [PagosController::class, 'devolver'])->name('ajustes.pagos.devolver');
                });

                // ---- VERI*FACTU
                Route::middleware('permiso:ajustes.acceso')->group(function () {
                    Route::get('verifactu', [VerifactuController::class, 'index'])->name('verifactu');
                    Route::post('verifactu/activar', [VerifactuController::class, 'activar'])->name('verifactu.activar');
                    Route::post('verifactu/certificado', [VerifactuController::class, 'subirCertificado'])->name('verifactu.certificado');
                    Route::post('verifactu/certificado/borrar', [VerifactuController::class, 'borrarCertificado'])->name('verifactu.certificado.borrar');
                    Route::post('verifactu/enviar', [VerifactuController::class, 'enviarPendientes'])->name('verifactu.enviar');
                    Route::post('verifactu/{registro}/reintentar', [VerifactuController::class, 'reintentar'])->name('verifactu.reintentar');
                    Route::get('verifactu/{registro}/xml', [VerifactuController::class, 'verXml'])->name('verifactu.xml');
                });

                // ---- Informes
                Route::middleware('permiso:informes.ver,informes.ver_propios')->group(function () {
                    Route::get('informes', [InformesController::class, 'index'])->name('informes');
                    Route::get('informes/exportar', [InformesController::class, 'exportar'])->name('informes.exportar');
                });
            });
        });
    });
});
