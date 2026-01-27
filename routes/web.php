<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotiController;
use App\Http\Controllers\CotioController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculosController;
use App\Http\Controllers\InventarioLabController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CheckAuth;
use App\Http\Middleware\EnsureSessionActive;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\VariableRequeridaController;
use App\Http\Controllers\InventarioMuestreoController;
use App\Http\Controllers\MuestrasController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\CheckAdminOrRole;
use App\Http\Controllers\SimpleNotificationController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\VentasController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\MetodoMuestreoController;
use App\Http\Controllers\MetodoAnalisisController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MetodosController;
use App\Http\Controllers\LeyNormativaController;
use App\Http\Controllers\VariableController;
use App\Http\Controllers\CustomerController;



// Rutas de autenticación
Route::middleware([EnsureSessionActive::class])->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});



// Rutas accesibles solo para usuarios autenticados (sin importar rol)
Route::middleware(CheckAuth::class)->group(function () {
    // Rutas de perfil de usuario
    Route::get('/auth/{id}', [AuthController::class, 'show'])->name('auth.show');
    Route::get('/auth/{id}/edit', [AuthController::class, 'edit'])->name('auth.edit');
    Route::put('/auth/{id}', [AuthController::class, 'update'])->name('auth.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Rutas de tareas
    Route::get('/mis-tareas', [CotiController::class, 'showTareas'])->name('mis-tareas');
    Route::get('/tareas-all/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'showTareasAll'])->name('tareas.all.show');
    Route::get('/tareas/{cotio_numcoti}/{cotio_item}/{cotio_subitem}', [CotioController::class, 'showTarea'])->name('tareas.show');
    
    // Ruta universal QR que redirije según rol del usuario
    Route::get('/qr-universal/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'qrUniversalRedirect'])->name('qr.universal');
    
    // Ruta para selector de vista para administradores
    Route::get('/qr-selector/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'qrViewSelector'])->name('qr.selector');
    Route::put('/tareas/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/estado', [CotioController::class, 'updateEstado'])->name('tareas.updateEstado');
    Route::put('/tareas/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/fecha-carga', [CotioController::class, 'updateFechaCarga'])->name('tareas.updateFechaCarga');
    Route::post('/asignar-identificacion-muestra', [CotioController::class, 'asignarIdentificacionMuestra'])->name('asignar.identificacion-muestra');
    Route::post('/asignar-suspension-muestra', [CotioController::class, 'asignarSuspensionMuestra'])->name('asignar.suspension-muestra');
    Route::put('/tareas/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/resultado', [CotioController::class, 'updateResultado'])->name('tareas.updateResultado');
    Route::put('/tareas/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/resultado-only', [CotioController::class, 'onlyUpdateResultado'])->name('tareas.onlyUpdateResultado');
    Route::put('/tareas/{instance}/mediciones', [CotioController::class, 'updateMediciones'])->name('tareas.updateMediciones');
    Route::put('/tareas/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/herramientas', [CotioController::class, 'updateHerramientas'])->name('tareas.updateHerramientas');

    // Rutas de ordenes
    Route::get('/mis-ordenes', [OrdenController::class, 'showOrdenes'])->name('mis-ordenes');
    Route::get('/ordenes-all/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [OrdenController::class, 'showOrdenesAll'])->name('ordenes.all.show');

    Route::get('auth/{id}/seguridad', [AuthController::class, 'showSecurity'])->name('auth.security');
    Route::get('auth/{id}/ayuda', [AuthController::class, 'showHelp'])->name('auth.help');

    Route::put('/instancias/{instancia}/herramientas', [OrdenController::class, 'updateHerramientas'])->name('instancias.update-herramientas');
    Route::get('/instancias/{instancia}/herramientas', [OrdenController::class, 'apiHerramientasInstancia']);

    
    Route::get('/notificaciones', [SimpleNotificationController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/{id}/leida', [SimpleNotificationController::class, 'marcarComoLeida'])->name('notificaciones.leida');
    Route::post('/notificaciones/leer-todas', [SimpleNotificationController::class, 'marcarTodasComoLeidas'])->name('notificaciones.leer-todas');
    Route::post('/notificaciones/marcar-leidas', [SimpleNotificationController::class, 'marcarLeidas'])->name('notificaciones.marcar-leidas');
    
    // Ruta para usuarios clientes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
});

// Rutas para usuarios con nivel 900 o más (admin)
Route::middleware([CheckAdminOrRole::class])->group(function () {
    // Dashboard
    Route::get('/', [CotiController::class, 'index'])->name('cotizaciones.index');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Gestión de cotizaciones
    Route::get('/cotizaciones/{cotizacion}', [CotiController::class, 'showDetalle'])->name('cotizaciones.ver-detalle');
    Route::get('/cotizaciones/{cotizacion}/pdf', [CotiController::class, 'generateFullPdf'])->name('cotizaciones.full.pdf');
    Route::get('/cotizaciones/{cotizacion}/qr/all', [CotiController::class, 'printAllQr'])->name('cotizaciones.qr.all');
    Route::get('/cotizaciones/{cotizacion}/item/{item}/qr', [CotioController::class, 'generateItemQr'])->name('cotizaciones.item.qr');
    Route::get('/cotizaciones/{cotizacion}/categoria/{item}/{instance}', [CotioController::class, 'verCategoria'])->name('categoria.ver');
    
    // Gestión de tareas
    Route::post('/asignar-fechas', [CotioController::class, 'asignarFechas'])->name('asignar.fechas');
    Route::post('/tareas/actualizar-estado', [CotioController::class, 'actualizarEstado'])->name('tareas.actualizar-estado');
    Route::post('/asignar-frecuencia', [CotioController::class, 'asignarFrecuencia'])->name('asignar.frecuencia');
    Route::post('/asignar-identificacion', [CotioController::class, 'asignarIdentificacion'])->name('asignar.identificacion');
    Route::post('/tareas/pasar-muestreo', [CotioController::class, 'pasarMuestreo'])->name('tareas.pasar-muestreo');
    Route::post('/tareas/pasar-analisis', [OrdenController::class, 'pasarAnalisis'])->name('tareas.pasar-analisis');
    Route::post('/asignar-detalles', [CotioController::class, 'asignarDetalles'])->name('asignar.detalles');
    Route::post('/asignar-responsable-tarea', [CotioController::class, 'asignarResponsableTareaIndividual'])->name('asignar.responsable.tarea');
    Route::delete('/tareas/{cotizacion}/{item}/{subitem}/herramientas/{herramienta_id}', [CotioController::class, 'desasignarHerramienta'])->name('tareas.desasignar-herramienta');
    Route::delete('/tareas/{cotizacion}/{item}/{subitem}/vehiculos/{vehiculo_id}', [CotioController::class, 'desasignarVehiculo'])->name('tareas.desasignar-vehiculo');
    Route::post('/enable-ot/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'enableOt'])->name('categorias.enable-ot');
    Route::post('/disable-ot/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'disableOt'])->name('categorias.disable-ot');


    // Gestión de usuarios
    Route::get('/users', [UserController::class, 'showUsers'])->name('users.showUsers');
    Route::get('/users/create', [UserController::class, 'createUser'])->name('users.createUser');
    Route::post('/users', [UserController::class, 'storeUser'])->name('users.storeUser');
    Route::get('/users/{usu_codigo}', [UserController::class, 'showUser'])->name('users.showUser');
    Route::put('/users/{usu_codigo}', [UserController::class, 'update'])->name('users.update');
    Route::get('/users/exportar/excel', [UserController::class, 'exportar'])->name('users.exportar');
    

    // Gestión de sectores
    Route::get('/sectores', [UserController::class, 'showSectores'])->name('sectores.showSectores');
    Route::get('/sectores/create', [UserController::class, 'createSector'])->name('sectores.create');
    Route::get('/sectores/{sector_codigo}', [UserController::class, 'showSector'])->name('sectores.showSector');
    Route::put('/sectores/{sector_codigo}', [UserController::class, 'updateSector'])->name('sectores.updateSector');
    Route::post('/sectores', [UserController::class, 'storeSector'])->name('sectores.store');


    // Gestión de vehículos
    Route::get('/vehiculos', [VehiculosController::class, 'index'])->name('vehiculos.index');
    Route::get('/vehiculos/create', [VehiculosController::class, 'create'])->name('vehiculos.create');
    Route::post('/vehiculos', [VehiculosController::class, 'store'])->name('vehiculos.store');
    Route::get('/api/vehiculos', [VehiculosController::class, 'getVehiculosApi'])->name('vehiculos.api');
    Route::get('/vehiculos/{id}/edit', [VehiculosController::class, 'getVehiculo'])->name('vehiculos.show');
    Route::put('/vehiculos/{id}', [VehiculosController::class, 'update'])->name('vehiculos.update');
    Route::delete('/vehiculos/{id}', [VehiculosController::class, 'destroy'])->name('vehiculos.destroy');

    // Gestión de variables requeridas
    Route::get('/variables-requeridas', [VariableRequeridaController::class, 'index'])->name('variables-requeridas.index');
    Route::get('/variables-requeridas/create', [VariableRequeridaController::class, 'create'])->name('variables-requeridas.create');
    Route::post('/variables-requeridas', [VariableRequeridaController::class, 'store'])->name('variables-requeridas.store');
    Route::get('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'show'])->name('variables-requeridas.show');
    Route::get('/variables-requeridas/{variableRequerida}/edit', [VariableRequeridaController::class, 'edit'])->name('variables-requeridas.edit');
    Route::put('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'update'])->name('variables-requeridas.update');
    Route::delete('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'destroy'])->name('variables-requeridas.destroy');
    
    // Rutas para grupos de variables
    Route::get('/variables-requeridas/grupo/{groupName}/editar', [VariableRequeridaController::class, 'editGroup'])
    ->name('variables-requeridas.edit-group');

    Route::put('/variables-requeridas/grupo/{groupName}', [VariableRequeridaController::class, 'updateGroup'])
    ->name('variables-requeridas.update-group');
    
    // Gestión de inventarios
    Route::get('/inventarios', [InventarioLabController::class, 'index'])->name('inventarios.index');
    Route::get('/inventarios/create', [InventarioLabController::class, 'create'])->name('inventarios.create');
    Route::post('/inventarios', [InventarioLabController::class, 'store'])->name('inventarios.store');
    Route::get('/inventarios/{id}/edit', [InventarioLabController::class, 'show'])->name('inventarios.show');
    Route::put('/inventarios/{id}', [InventarioLabController::class, 'update'])->name('inventarios.update');
    Route::delete('/inventarios/{id}', [InventarioLabController::class, 'destroy'])->name('inventarios.destroy');

    Route::get('/api/instancias/{instancia}/herramientas', [App\Http\Controllers\OrdenController::class, 'apiHerramientasInstancia']);
    Route::post('/api/get-responsables-analisis', [App\Http\Controllers\OrdenController::class, 'getResponsablesAnalisis']);
    Route::get('/api/usuario/{codigo}', [App\Http\Controllers\UserController::class, 'getUsuarioInfo']);

    // Gestión de inventarios de muestreo
    Route::get('/inventarios-muestreo', [InventarioMuestreoController::class, 'index'])->name('inventarios-muestreo.index');
    Route::get('/inventarios-muestreo/create', [InventarioMuestreoController::class, 'create'])->name('inventarios-muestreo.create');
    Route::post('/inventarios-muestreo', [InventarioMuestreoController::class, 'store'])->name('inventarios-muestreo.store');
    Route::get('/inventarios-muestreo/{id}/edit', [InventarioMuestreoController::class, 'show'])->name('inventarios-muestreo.show');
    Route::put('/inventarios-muestreo/{id}', [InventarioMuestreoController::class, 'update'])->name('inventarios-muestreo.update');
    Route::delete('/inventarios-muestreo/{id}', [InventarioMuestreoController::class, 'destroy'])->name('inventarios-muestreo.destroy');


    // Gestión de ordenes de trabajo
    Route::get('/ordenes', [OrdenController::class, 'index'])->name('ordenes.index');
    Route::get('/ordenes/{cotizacion}', [OrdenController::class, 'showDetalle'])->name('ordenes.ver-detalle');
    Route::get('/ordenes/{cotizacion}/categoria/{item}/{instance}', [OrdenController::class, 'verOrden'])->name('categoria.verOrden');
    Route::post('/asignar-detalles-analisis', [OrdenController::class, 'asignarDetallesAnalisis'])->name('asignar.detalles-analisis');
    Route::post('/ordenes/{ordenId}/asignacion-masiva', [OrdenController::class, 'asignacionMasiva'])->name('ordenes.asignacionMasiva');
    Route::post('/ordenes/finalizar-todas', [OrdenController::class, 'finalizarTodas'])->name('ordenes.finalizar-todas');
    Route::post('/ordenes/finalizar-analisis-seleccionados', [OrdenController::class, 'finalizarAnalisisSeleccionados'])->name('ordenes.finalizar-analisis-seleccionados');
    Route::post('/ordenes/asignar-responsables-analisis-seleccionados', [OrdenController::class, 'asignarResponsablesAnalisisSeleccionados'])->name('ordenes.asignar-responsables-analisis-seleccionados');
    Route::post('/ordenes/{ordenId}/remover-responsable', [OrdenController::class, 'removerResponsable'])->name('ordenes.remover-responsable');
    Route::put('/ordenes/{cotio_numcoti}/editar-responsables', [OrdenController::class, 'editarResponsables'])->name('ordenes.editar-responsables');
    Route::post('/ordenes/{cotio_numcoti}/editar-responsables', [OrdenController::class, 'editarResponsables'])->name('ordenes.editar-responsables');
    Route::delete('/ordenes/{cotio_numcoti}/quitar-responsable', [OrdenController::class, 'quitarResponsable'])->name('ordenes.quitar-responsable');
    Route::post('/ordenes/{cotio_numcoti}/quitar-responsable', [OrdenController::class, 'quitarResponsable'])->name('ordenes.quitar-responsable');
    Route::get('/debug/responsables/{cotio_numcoti}', [OrdenController::class, 'debugResponsables'])->name('debug.responsables');
    Route::delete('/debug/forzar-eliminacion/{cotio_numcoti}', [OrdenController::class, 'forzarEliminacion'])->name('debug.forzar-eliminacion');
    Route::post('/ordenes/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/enable-informe', [OrdenController::class, 'enableInforme'])->name('ordenes.enable-informe');
    Route::post('/ordenes/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/disable-informe', [OrdenController::class, 'disableInforme'])->name('ordenes.disable-informe');
    Route::get('/ordenes/{instancia_id}/informe-preliminar', [OrdenController::class, 'verInformePreliminar'])->name('ordenes.informe-preliminar');
    Route::post('/ordenes/{instancia_id}/aprobar-informe', [OrdenController::class, 'aprobarInforme'])->name('ordenes.aprobar-informe');
    Route::post('/ordenes/{cotizacion}/deshacer-asignaciones', [OrdenController::class, 'deshacerAsignaciones'])->name('ordenes.deshacer-asignaciones');
    Route::post('/ordenes/actualizar-estado', [OrdenController::class, 'actualizarEstado'])->name('ordenes.actualizar-estado');
    Route::post('/ordenes/{cotizacion}/deshacer-asignaciones', [OrdenController::class, 'deshacerAsignaciones'])->name('ordenes.deshacer-asignaciones');
    Route::post('/ordenes/{instance}/request-review', [OrdenController::class, 'requestReview'])->name('ordenes.request-review');
    Route::post('/ordenes/{instance}/request-review-cancel', [OrdenController::class, 'requestReviewCancel'])->name('ordenes.request-review-cancel');

    // Gestión de muestras
    Route::get('/muestras', [MuestrasController::class, 'index'])->name('muestras.index');
    Route::get('/show/{coti_num}', [MuestrasController::class, 'show'])->name('muestras.show');
    Route::get('/muestras/{cotizacion}/categoria/{item}/{instance}', [MuestrasController::class, 'verMuestra'])->name('categoria.verMuestra');
    Route::post('/asignar-detalles-muestra', [MuestrasController::class, 'asignarDetallesMuestra'])->name('asignar.detalles-muestra');
    Route::get('/muestras/{cotizacion}/categoria/{item}/{instance}/ver', [MuestrasController::class, 'verMuestra'])->name('muestras.ver');
    Route::post('/muestras/asignacion-masiva', [MuestrasController::class, 'asignacionMasiva'])->name('muestras.asignacion-masiva');
    Route::post('/muestras/finalizar-todas', [MuestrasController::class, 'finalizarTodas'])->name('muestras.finalizar-todas');
    Route::post('/muestras/remover-responsable', [MuestrasController::class, 'removerResponsable'])->name('muestras.remover-responsable');
    Route::get('/muestras/{instancia}/datos-recoordinacion', [MuestrasController::class, 'getDatosRecoordinacion']);
    Route::post('/muestras/recoordinar', [MuestrasController::class, 'recoordinar'])->name('muestras.recoordinar');
    Route::put('/muestras/update-variable', [MuestrasController::class, 'updateVariable'])->name('muestras.updateVariable');
    Route::put('/muestras/update-all-data', [MuestrasController::class, 'updateAllData'])->name('muestras.updateAllData');
    Route::post('/muestras/update-all-data', [MuestrasController::class, 'updateAllData'])->name('muestras.updateAllData');
    Route::post('/muestras/update-all-data', [MuestrasController::class, 'updateAllData'])->name('muestras.updateAllData');
    Route::post('/muestras/pasar-directo-a-ot/{cotio_numcoti}/{cotio_item}/{instance_number}', [MuestrasController::class, 'pasarDirectoAOT'])->name('muestras.pasar-directo-a-ot');
    Route::delete('/muestras/quitar-directo-a-ot/{cotio_numcoti}/{cotio_item}/{instance_number}', [MuestrasController::class, 'quitarDirectoAOT'])->name('muestras.quitar-directo-a-ot');
    Route::delete('/muestras/quitar-directo-a-ot-from-coordinador/{cotio_numcoti}/{cotio_item}/{instance_number}', [MuestrasController::class, 'quitarDirectoAOTFromCoordinador'])->name('muestras.quitar-directo-a-ot-from-coordinador');

    // Rutas para gestionar responsables de muestreo
    Route::put('/muestras/editar-responsables', [MuestrasController::class, 'editarResponsablesMuestreo'])->name('muestras.editar-responsables-muestreo');
    Route::post('/muestras/editar-responsables', [MuestrasController::class, 'editarResponsablesMuestreo'])->name('muestras.editar-responsables-muestreo');
    Route::post('/muestras/quitar-responsable-muestreo', [MuestrasController::class, 'quitarResponsableMuestreo'])->name('muestras.quitar-responsable-muestreo');
    Route::delete('/muestras/quitar-responsable-muestreo', [MuestrasController::class, 'quitarResponsableMuestreo'])->name('muestras.quitar-responsable-muestreo');
    Route::post('/api/get-responsables-muestreo', [MuestrasController::class, 'getResponsablesMuestreo'])->name('api.get-responsables-muestreo');



    //informes
    Route::get('/informes', [InformeController::class, 'index'])->name('informes.index');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}', [InformeController::class, 'show'])->name('informes.show');
    Route::get('/informes/pdf-masivo/{cotizacion}', [InformeController::class, 'generarPdfMasivo'])->name('informes.pdf-masivo');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}/pdf', [InformeController::class, 'generarPdf'])->name('informes.pdf');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}/firmar', [InformeController::class, 'firmarInforme'])->name('informes.firmar');
    Route::get('/informes-api/{cotio_numcoti}/{cotio_item}/{instance_number}', [InformeController::class, 'getInformeData'])->name('api.informes.get');
    Route::put('/informes-api/{cotio_numcoti}/{cotio_item}/{instance_number}', [InformeController::class, 'updateInforme'])->name('api.informes.update');
    
    // Rutas de callbacks para firma digital
    Route::get('/firma/exitosa', [InformeController::class, 'firmaExitosa'])->name('firma.exitosa');
    Route::get('/firma/error', [InformeController::class, 'firmaError'])->name('firma.error');
    Route::get('/firma/rechazada', [InformeController::class, 'firmaRechazada'])->name('firma.rechazada');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}/descargar-firmado', [InformeController::class, 'descargarDocumentoFirmado'])->name('informes.descargar-firmado');
    Route::post('/ordenes/actualizar-estado', [OrdenController::class, 'actualizarEstado'])->name('ordenes.actualizar-estado');

    // Gestión de calibraciones del inventario de laboratorio
    Route::get('/calibracion', [App\Http\Controllers\CalibracionController::class, 'index'])->name('calibracion.index');
    Route::post('/calibracion/ejecutar-verificacion', [App\Http\Controllers\CalibracionController::class, 'ejecutarVerificacion'])->name('calibracion.ejecutar-verificacion');
    Route::get('/calibracion/estadisticas', [App\Http\Controllers\CalibracionController::class, 'estadisticas'])->name('calibracion.estadisticas');
    Route::post('/calibracion/notificaciones/{id}/leida', [App\Http\Controllers\CalibracionController::class, 'marcarLeida'])->name('calibracion.marcar-leida');
    Route::get('/calibracion/equipos-proximos', [App\Http\Controllers\CalibracionController::class, 'equiposProximos'])->name('calibracion.equipos-proximos');
    Route::get('/calibracion/equipos-vencidos', [App\Http\Controllers\CalibracionController::class, 'equiposVencidos'])->name('calibracion.equipos-vencidos');

    Route::get('/facturacion', [App\Http\Controllers\FacturacionController::class, 'index'])->name('facturacion.index');
    Route::get('/facturacion/listado', [App\Http\Controllers\FacturacionController::class, 'listarFacturas'])->name('facturacion.listado');
    Route::get('/facturacion/detalle/{id}', [App\Http\Controllers\FacturacionController::class, 'verFactura'])->name('facturacion.ver');
    Route::get('/facturacion/{factura}/descargar', [App\Http\Controllers\FacturacionController::class, 'descargar'])->name('facturacion.descargar');
    Route::get('/facturacion/facturar/{cotizacion}', [App\Http\Controllers\FacturacionController::class, 'facturar'])->name('facturacion.show');
    Route::post('/facturacion/facturar/{cotizacion}', [App\Http\Controllers\FacturacionController::class, 'generarFacturaArca'])->name('facturacion.facturar');

    //Auditoria
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('/auditoria/exportar', [AuditoriaController::class, 'exportar'])->name('auditoria.exportar');
    Route::get('/auditoria/preview', [AuditoriaController::class, 'preview'])->name('auditoria.preview'); // Para debug

    // Route::get('metodos-muestreo', [MetodoMuestreoController::class, 'index'])->name('metodos-muestreo.index');
    // Route::get('metodos-muestreo/create', [MetodoMuestreoController::class, 'create'])->name('metodos-muestreo.create');
    // Route::post('metodos-muestreo/store', [MetodoMuestreoController::class, 'store'])->name('metodos-muestreo.store');
    // Route::get('metodos-muestreo/{metodoMuestreo}', [MetodoMuestreoController::class, 'show'])->name('metodos-muestreo.show');
    // Route::get('metodos-muestreo/{metodoMuestreo}/edit', [MetodoMuestreoController::class, 'edit'])->name('metodos-muestreo.edit');
    // Route::put('metodos-muestreo/{metodoMuestreo}', [MetodoMuestreoController::class, 'update'])->name('metodos-muestreo.update');
    // Route::delete('metodos-muestreo/{metodoMuestreo}', [MetodoMuestreoController::class, 'delete'])->name('metodos-muestreo.delete');
    
    
    // // Métodos de Análisis
    // Route::get('metodos-analisis', [MetodoAnalisisController::class, 'index'])->name('metodos-analisis.index');
    // Route::get('metodos-analisis/create', [MetodoAnalisisController::class, 'create'])->name('metodos-analisis.create');
    // Route::post('metodos-analisis/store', [MetodoAnalisisController::class, 'store'])->name('metodos-analisis.store');
    // Route::get('metodos-analisis/{metodoAnalisis}', [MetodoAnalisisController::class, 'show'])->name('metodos-analisis.show');
    // Route::get('metodos-analisis/{metodoAnalisis}/edit', [MetodoAnalisisController::class, 'edit'])->name('metodos-analisis.edit');
    // Route::put('metodos-analisis/{metodoAnalisis}', [MetodoAnalisisController::class, 'update'])->name('metodos-analisis.update');
    // Route::delete('metodos-analisis/{metodoAnalisis}', [MetodoAnalisisController::class, 'delete'])->name('metodos-analisis.delete');

    //Métodos
    Route::get('metodos',[MetodosController::class,'index'])->name('metodos.index');
    Route::get('metodos/create', [MetodosController::class, 'create'])->name('metodos.create');
    Route::post('metodos/store', [MetodosController::class, 'store'])->name('metodos.store');
    Route::get('metodos/{metodo}/edit', [MetodosController::class, 'edit'])->name('metodos.edit');
    Route::put('metodos/{metodo}', [MetodosController::class, 'update'])->name('metodos.update');
    Route::delete('metodos/{metodo}', [MetodosController::class, 'delete'])->name('metodos.delete');

    //Items
    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('items/store', [ItemController::class, 'store'])->name('items.store');
    
    // Importación masiva (debe ir antes de items/{cotio_items})
    Route::get('items/importar', [ItemController::class, 'showImportar'])->name('items.importar');
    Route::post('items/importar/procesar', [ItemController::class, 'procesarImportacion'])->name('items.importar-procesar');
    Route::get('items/importar/plantilla', [ItemController::class, 'descargarPlantilla'])->name('items.descargar-plantilla');
    
    // Cambios masivos de precios (debe ir antes de items/{cotio_items})
    Route::get('items/precios/cambios-masivos', [ItemController::class, 'showCambiosMasivos'])->name('items.cambios-masivos-precios');
    Route::post('items/precios/aplicar-cambios-masivos', [ItemController::class, 'aplicarCambiosMasivos'])->name('items.aplicar-cambios-masivos');
    Route::get('items/precios/historial', [ItemController::class, 'historialPrecios'])->name('items.historial-precios');
    Route::post('items/precios/revertir/{operacionId}', [ItemController::class, 'revertirCambios'])->name('items.revertir-cambios');
    
    // Rutas con parámetros (deben ir al final)
    Route::get('items/{cotio_items}', [ItemController::class, 'show'])->name('items.show');
    Route::get('items/{cotio_items}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{cotio_items}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{cotio_items}', [ItemController::class, 'delete'])->name('items.delete');
    

    // Leyes y Normativas
    Route::get('leyes-normativas', [LeyNormativaController::class, 'index'])->name('leyes-normativas.index');
    Route::get('leyes-normativas/create', [LeyNormativaController::class, 'create'])->name('leyes-normativas.create');
    Route::post('leyes-normativas/store', [LeyNormativaController::class, 'store'])->name('leyes-normativas.store');
    // Rutas específicas deben ir antes de las rutas con parámetros dinámicos
    Route::get('leyes-normativas/export/template', [LeyNormativaController::class, 'exportTemplate'])->name('leyes-normativas.export.template');
    Route::get('leyes-normativas/import', [LeyNormativaController::class, 'showImport'])->name('leyes-normativas.import');
    Route::post('leyes-normativas/import', [LeyNormativaController::class, 'import'])->name('leyes-normativas.import.process');
    // Rutas con parámetros dinámicos al final
    Route::get('leyes-normativas/{leyNormativa}', [LeyNormativaController::class, 'show'])->name('leyes-normativas.show');
    Route::get('leyes-normativas/{leyNormativa}/edit', [LeyNormativaController::class, 'edit'])->name('leyes-normativas.edit');
    Route::put('leyes-normativas/{leyNormativa}', [LeyNormativaController::class, 'update'])->name('leyes-normativas.update');
    Route::get('leyes-normativas/{leyNormativa}/delete', [LeyNormativaController::class, 'delete'])->name('leyes-normativas.delete');
    Route::delete('leyes-normativas/{leyNormativa}', [LeyNormativaController::class, 'destroy'])->name('leyes-normativas.destroy');
    Route::delete('leyes-normativas/{leyNormativa}/remove-variable', [LeyNormativaController::class, 'removeVariable'])->name('leyes-normativas.remove-variable');
    
    // Variables
    Route::get('variables', [VariableController::class, 'index'])->name('variables.index');
    Route::get('variables/create', [VariableController::class, 'create'])->name('variables.create');
    Route::post('variables/store', [VariableController::class, 'store'])->name('variables.store');
    Route::get('variables/{variable}', [VariableController::class, 'show'])->name('variables.show');
    Route::get('variables/{variable}/edit', [VariableController::class, 'edit'])->name('variables.edit');
    Route::put('variables/{variable}', [VariableController::class, 'update'])->name('variables.update');
    Route::delete('variables/{variable}', [VariableController::class, 'delete'])->name('variables.delete');
    
    // API Routes para variables
    Route::get('variables-api', [App\Http\Controllers\VariableController::class, 'apiIndex'])->name('variables.api.index');
    Route::post('variables-api', [App\Http\Controllers\VariableController::class, 'apiStore'])->name('variables.api.store');
    
    // API Route para cotio_items para leyes-normativas
    Route::get('cotio-items-api', [App\Http\Controllers\ItemController::class, 'apiIndexForLeyesNormativas'])->name('cotio-items.api.index');
});


//coordinador_analisis
Route::middleware([CheckAdminOrRole::class])->group(function () {
    Route::get('/', [CotiController::class, 'index'])->name('cotizaciones.index');

    Route::get('/dashboard/analisis', [DashboardController::class, 'dashboardAnalisis'])->name('dashboard.analisis');
    Route::get('/dashboard/debug-analisis', [DashboardController::class, 'debugAnalisis'])->name('dashboard.debug-analisis');
    Route::get('/ordenes/debug-ordenamiento', [OrdenController::class, 'debugOrdenamiento'])->name('ordenes.debug-ordenamiento');

    Route::get('/cotizaciones/{cotizacion}', [CotiController::class, 'showDetalle'])->name('cotizaciones.ver-detalle');
    Route::get('/cotizaciones/{cotizacion}/pdf', [CotiController::class, 'generateFullPdf'])->name('cotizaciones.full.pdf');
    Route::get('/cotizaciones/{cotizacion}/qr/all', [CotiController::class, 'printAllQr'])->name('cotizaciones.qr.all');
    Route::get('/cotizaciones/{cotizacion}/item/{item}/qr', [CotioController::class, 'generateItemQr'])->name('cotizaciones.item.qr');
    Route::get('/cotizaciones/{cotizacion}/categoria/{item}/{instance}', [CotioController::class, 'verCategoria'])->name('categoria.ver');
    
    Route::post('/asignar-fechas', [CotioController::class, 'asignarFechas'])->name('asignar.fechas');
    Route::post('/tareas/actualizar-estado', [CotioController::class, 'actualizarEstado'])->name('tareas.actualizar-estado');
    Route::post('/asignar-frecuencia', [CotioController::class, 'asignarFrecuencia'])->name('asignar.frecuencia');
    Route::post('/asignar-identificacion', [CotioController::class, 'asignarIdentificacion'])->name('asignar.identificacion');
    Route::post('/tareas/pasar-muestreo', [CotioController::class, 'pasarMuestreo'])->name('tareas.pasar-muestreo');
    Route::post('/tareas/pasar-analisis', [OrdenController::class, 'pasarAnalisis'])->name('tareas.pasar-analisis');
    Route::post('/asignar-detalles', [CotioController::class, 'asignarDetalles'])->name('asignar.detalles');
    Route::post('/asignar-responsable-tarea', [CotioController::class, 'asignarResponsableTareaIndividual'])->name('asignar.responsable.tarea');
    Route::delete('/tareas/{cotizacion}/{item}/{subitem}/herramientas/{herramienta_id}', [CotioController::class, 'desasignarHerramienta'])->name('tareas.desasignar-herramienta');
    Route::delete('/tareas/{cotizacion}/{item}/{subitem}/vehiculos/{vehiculo_id}', [CotioController::class, 'desasignarVehiculo'])->name('tareas.desasignar-vehiculo');
    Route::post('/enable-ot/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'enableOt'])->name('categorias.enable-ot');
    Route::post('/disable-ot/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'disableOt'])->name('categorias.disable-ot');


    // Gestión de inventarios
    Route::get('/inventarios', [InventarioLabController::class, 'index'])->name('inventarios.index');
    Route::get('/inventarios/create', [InventarioLabController::class, 'create'])->name('inventarios.create');
    Route::post('/inventarios', [InventarioLabController::class, 'store'])->name('inventarios.store');
    Route::get('/inventarios/{id}/edit', [InventarioLabController::class, 'show'])->name('inventarios.show');
    Route::put('/inventarios/{id}', [InventarioLabController::class, 'update'])->name('inventarios.update');
    Route::delete('/inventarios/{id}', [InventarioLabController::class, 'destroy'])->name('inventarios.destroy');

    // Gestión de variables requeridas
    Route::get('/variables-requeridas', [VariableRequeridaController::class, 'index'])->name('variables-requeridas.index');
    Route::get('/variables-requeridas/create', [VariableRequeridaController::class, 'create'])->name('variables-requeridas.create');
    Route::post('/variables-requeridas', [VariableRequeridaController::class, 'store'])->name('variables-requeridas.store');
    Route::get('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'show'])->name('variables-requeridas.show');
    Route::get('/variables-requeridas/{variableRequerida}/edit', [VariableRequeridaController::class, 'edit'])->name('variables-requeridas.edit');
    Route::put('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'update'])->name('variables-requeridas.update');
    Route::delete('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'destroy'])->name('variables-requeridas.destroy');

    // Rutas para grupos de variables
    Route::get('/variables-requeridas/grupo/{groupName}/editar', [VariableRequeridaController::class, 'editGroup'])
    ->name('variables-requeridas.edit-group');

    Route::put('/variables-requeridas/grupo/{groupName}', [VariableRequeridaController::class, 'updateGroup'])
    ->name('variables-requeridas.update-group');
    
    Route::get('/api/instancias/{instancia}/herramientas', [App\Http\Controllers\OrdenController::class, 'apiHerramientasInstancia']);
    Route::post('/api/get-responsables-analisis', [App\Http\Controllers\OrdenController::class, 'getResponsablesAnalisis']);
    Route::get('/api/usuario/{codigo}', [App\Http\Controllers\UserController::class, 'getUsuarioInfo']);

    // Gestión de ordenes de trabajo
    Route::get('/ordenes', [OrdenController::class, 'index'])->name('ordenes.index');
    Route::get('/ordenes/{cotizacion}', [OrdenController::class, 'showDetalle'])->name('ordenes.ver-detalle');
    Route::get('/ordenes/{cotizacion}/categoria/{item}/{instance}', [OrdenController::class, 'verOrden'])->name('categoria.verOrden');
    Route::post('/asignar-detalles-analisis', [OrdenController::class, 'asignarDetallesAnalisis'])->name('asignar.detalles-analisis');
    Route::post('/ordenes/{ordenId}/asignacion-masiva', [OrdenController::class, 'asignacionMasiva'])->name('ordenes.asignacionMasiva');
    Route::post('/ordenes/finalizar-todas', [OrdenController::class, 'finalizarTodas'])->name('ordenes.finalizar-todas');
    Route::post('/ordenes/finalizar-analisis-seleccionados', [OrdenController::class, 'finalizarAnalisisSeleccionados'])->name('ordenes.finalizar-analisis-seleccionados');
    Route::post('/ordenes/asignar-responsables-analisis-seleccionados', [OrdenController::class, 'asignarResponsablesAnalisisSeleccionados'])->name('ordenes.asignar-responsables-analisis-seleccionados');
    Route::post('/ordenes/{ordenId}/remover-responsable', [OrdenController::class, 'removerResponsable'])->name('ordenes.remover-responsable');
    Route::put('/ordenes/{cotio_numcoti}/editar-responsables', [OrdenController::class, 'editarResponsables'])->name('ordenes.editar-responsables');
    Route::post('/ordenes/{cotio_numcoti}/editar-responsables', [OrdenController::class, 'editarResponsables'])->name('ordenes.editar-responsables');
    Route::delete('/ordenes/{cotio_numcoti}/quitar-responsable', [OrdenController::class, 'quitarResponsable'])->name('ordenes.quitar-responsable');
    Route::post('/ordenes/{cotio_numcoti}/quitar-responsable', [OrdenController::class, 'quitarResponsable'])->name('ordenes.quitar-responsable');
    Route::get('/debug/responsables/{cotio_numcoti}', [OrdenController::class, 'debugResponsables'])->name('debug.responsables');
    Route::delete('/debug/forzar-eliminacion/{cotio_numcoti}', [OrdenController::class, 'forzarEliminacion'])->name('debug.forzar-eliminacion');
    Route::post('/ordenes/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/enable-informe', [OrdenController::class, 'enableInforme'])->name('ordenes.enable-informe');
    Route::post('/ordenes/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}/disable-informe', [OrdenController::class, 'disableInforme'])->name('ordenes.disable-informe');
    Route::get('/ordenes/{instancia_id}/informe-preliminar', [OrdenController::class, 'verInformePreliminar'])->name('ordenes.informe-preliminar');
    Route::post('/ordenes/{instancia_id}/aprobar-informe', [OrdenController::class, 'aprobarInforme'])->name('ordenes.aprobar-informe');
    Route::post('/ordenes/{cotizacion}/deshacer-asignaciones', [OrdenController::class, 'deshacerAsignaciones'])->name('ordenes.deshacer-asignaciones');
    Route::post('/ordenes/actualizar-estado', [OrdenController::class, 'actualizarEstado'])->name('ordenes.actualizar-estado');
    Route::post('/ordenes/{cotizacion}/deshacer-asignaciones', [OrdenController::class, 'deshacerAsignaciones'])->name('ordenes.deshacer-asignaciones');
    Route::post('/ordenes/{instance}/request-review', [OrdenController::class, 'requestReview'])->name('ordenes.request-review');
    Route::post('/ordenes/{instance}/request-review-cancel', [OrdenController::class, 'requestReviewCancel'])->name('ordenes.request-review-cancel');

    // Gestión de usuarios
    Route::get('/users', [UserController::class, 'showUsers'])->name('users.showUsers');
    Route::get('/users/create', [UserController::class, 'createUser'])->name('users.createUser');
    Route::post('/users', [UserController::class, 'storeUser'])->name('users.storeUser');
    Route::get('/users/{usu_codigo}', [UserController::class, 'showUser'])->name('users.showUser');
    Route::put('/users/{usu_codigo}', [UserController::class, 'update'])->name('users.update');
    Route::get('/users/exportar/excel', [UserController::class, 'exportar'])->name('users.exportar');
    

    // Gestión de sectores
    Route::get('/sectores', [UserController::class, 'showSectores'])->name('sectores.showSectores');
    Route::get('/sectores/create', [UserController::class, 'createSector'])->name('sectores.create');
    Route::get('/sectores/{sector_codigo}', [UserController::class, 'showSector'])->name('sectores.showSector');
    Route::put('/sectores/{sector_codigo}', [UserController::class, 'updateSector'])->name('sectores.updateSector');
    Route::post('/sectores', [UserController::class, 'storeSector'])->name('sectores.store');
});



//coordinador_muestreo
Route::middleware([CheckAdminOrRole::class])->group(function () {
    Route::get('/', [CotiController::class, 'index'])->name('cotizaciones.index');
    Route::get('/cotizaciones/{cotizacion}', [CotiController::class, 'showDetalle'])->name('cotizaciones.ver-detalle');
    Route::get('/cotizaciones/{cotizacion}/pdf', [CotiController::class, 'generateFullPdf'])->name('cotizaciones.full.pdf');
    Route::get('/cotizaciones/{cotizacion}/qr/all', [CotiController::class, 'printAllQr'])->name('cotizaciones.qr.all');
    Route::get('/cotizaciones/{cotizacion}/item/{item}/qr', [CotioController::class, 'generateItemQr'])->name('cotizaciones.item.qr');
    Route::get('/cotizaciones/{cotizacion}/categoria/{item}/{instance}', [CotioController::class, 'verCategoria'])->name('categoria.ver');

    Route::get('/dashboard/muestreo', [DashboardController::class, 'dashboardMuestreo'])->name('dashboard.muestreo');
    Route::post('/dashboard/muestreo/exportar', [DashboardController::class, 'exportarMuestrasMuestreo'])->name('dashboard.muestreo.exportar');
    Route::post('/dashboard/analisis/exportar', [DashboardController::class, 'exportarAnalisis'])->name('dashboard.analisis.exportar');
    
    Route::post('/asignar-fechas', [CotioController::class, 'asignarFechas'])->name('asignar.fechas');
    Route::post('/tareas/actualizar-estado', [CotioController::class, 'actualizarEstado'])->name('tareas.actualizar-estado');
    Route::post('/asignar-frecuencia', [CotioController::class, 'asignarFrecuencia'])->name('asignar.frecuencia');
    Route::post('/asignar-identificacion', [CotioController::class, 'asignarIdentificacion'])->name('asignar.identificacion');
    Route::post('/tareas/pasar-muestreo', [CotioController::class, 'pasarMuestreo'])->name('tareas.pasar-muestreo');
    Route::post('/tareas/pasar-analisis', [OrdenController::class, 'pasarAnalisis'])->name('tareas.pasar-analisis');
    Route::post('/asignar-detalles', [CotioController::class, 'asignarDetalles'])->name('asignar.detalles');
    Route::post('/asignar-responsable-tarea', [CotioController::class, 'asignarResponsableTareaIndividual'])->name('asignar.responsable.tarea');
    Route::delete('/tareas/{cotizacion}/{item}/{subitem}/herramientas/{herramienta_id}', [CotioController::class, 'desasignarHerramienta'])->name('tareas.desasignar-herramienta');
    Route::delete('/tareas/{cotizacion}/{item}/{subitem}/vehiculos/{vehiculo_id}', [CotioController::class, 'desasignarVehiculo'])->name('tareas.desasignar-vehiculo');
    Route::post('/enable-ot/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'enableOt'])->name('categorias.enable-ot');
    Route::post('/disable-ot/{cotio_numcoti}/{cotio_item}/{cotio_subitem}/{instance}', [CotioController::class, 'disableOt'])->name('categorias.disable-ot');


    // Gestión de inventarios de muestreo
    Route::get('/inventarios-muestreo', [InventarioMuestreoController::class, 'index'])->name('inventarios-muestreo.index');
    Route::get('/inventarios-muestreo/create', [InventarioMuestreoController::class, 'create'])->name('inventarios-muestreo.create');
    Route::post('/inventarios-muestreo', [InventarioMuestreoController::class, 'store'])->name('inventarios-muestreo.store');
    Route::get('/inventarios-muestreo/{id}/edit', [InventarioMuestreoController::class, 'show'])->name('inventarios-muestreo.show');
    Route::put('/inventarios-muestreo/{id}', [InventarioMuestreoController::class, 'update'])->name('inventarios-muestreo.update');
    Route::delete('/inventarios-muestreo/{id}', [InventarioMuestreoController::class, 'destroy'])->name('inventarios-muestreo.destroy');


    // Gestión de variables requeridas
    Route::get('/variables-requeridas', [VariableRequeridaController::class, 'index'])->name('variables-requeridas.index');
    Route::get('/variables-requeridas/create', [VariableRequeridaController::class, 'create'])->name('variables-requeridas.create');
    Route::post('/variables-requeridas', [VariableRequeridaController::class, 'store'])->name('variables-requeridas.store');
    Route::get('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'show'])->name('variables-requeridas.show');
    Route::get('/variables-requeridas/{variableRequerida}/edit', [VariableRequeridaController::class, 'edit'])->name('variables-requeridas.edit');
    Route::put('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'update'])->name('variables-requeridas.update');
    Route::delete('/variables-requeridas/{variableRequerida}', [VariableRequeridaController::class, 'destroy'])->name('variables-requeridas.destroy');

    // Rutas para grupos de variables
    Route::get('/variables-requeridas/grupo/{groupName}/editar', [VariableRequeridaController::class, 'editGroup'])
    ->name('variables-requeridas.edit-group');

    Route::put('/variables-requeridas/grupo/{groupName}', [VariableRequeridaController::class, 'updateGroup'])
    ->name('variables-requeridas.update-group');

    // Gestión de muestras
    Route::get('/muestras', [MuestrasController::class, 'index'])->name('muestras.index');
    Route::get('/show/{coti_num}', [MuestrasController::class, 'show']);
    Route::get('/muestras/{cotizacion}/categoria/{item}/{instance}', [MuestrasController::class, 'verMuestra'])->name('categoria.verMuestra');
    Route::post('/asignar-detalles-muestra', [MuestrasController::class, 'asignarDetallesMuestra'])->name('asignar.detalles-muestra');
    Route::get('/muestras/{cotizacion}/categoria/{item}/{instance}/ver', [MuestrasController::class, 'verMuestra'])->name('muestras.ver');
    Route::post('/muestras/asignacion-masiva', [MuestrasController::class, 'asignacionMasiva'])->name('muestras.asignacion-masiva');
    Route::post('/muestras/finalizar-todas', [MuestrasController::class, 'finalizarTodas'])->name('muestras.finalizar-todas');
    Route::post('/muestras/remover-responsable', [MuestrasController::class, 'removerResponsable'])->name('muestras.remover-responsable');
    Route::get('/muestras/{instancia}/datos-recoordinacion', [MuestrasController::class, 'getDatosRecoordinacion']);
    Route::post('/muestras/recoordinar', [MuestrasController::class, 'recoordinar'])->name('muestras.recoordinar');
    Route::put('/muestras/update-variable', [MuestrasController::class, 'updateVariable'])->name('muestras.updateVariable');
    Route::put('/muestras/update-all-data', [MuestrasController::class, 'updateAllData'])->name('muestras.updateAllData');
    Route::post('/muestras/update-all-data', [MuestrasController::class, 'updateAllData'])->name('muestras.updateAllData');


    // Gestión de vehículos
    Route::get('/vehiculos', [VehiculosController::class, 'index'])->name('vehiculos.index');
    Route::get('/vehiculos/create', [VehiculosController::class, 'create'])->name('vehiculos.create');
    Route::post('/vehiculos', [VehiculosController::class, 'store'])->name('vehiculos.store');
    Route::get('/api/vehiculos', [VehiculosController::class, 'getVehiculosApi'])->name('vehiculos.api');
    Route::get('/vehiculos/{id}/edit', [VehiculosController::class, 'getVehiculo'])->name('vehiculos.show');
    Route::put('/vehiculos/{id}', [VehiculosController::class, 'update'])->name('vehiculos.update');
    Route::delete('/vehiculos/{id}', [VehiculosController::class, 'destroy'])->name('vehiculos.destroy');


    // Gestión de usuarios
    Route::get('/users', [UserController::class, 'showUsers'])->name('users.showUsers');
    Route::get('/users/create', [UserController::class, 'createUser'])->name('users.createUser');
    Route::post('/users', [UserController::class, 'storeUser'])->name('users.storeUser');
    Route::get('/users/{usu_codigo}', [UserController::class, 'showUser'])->name('users.showUser');
    Route::put('/users/{usu_codigo}', [UserController::class, 'update'])->name('users.update');
    Route::get('/users/exportar/excel', [UserController::class, 'exportar'])->name('users.exportar');
    

    // Gestión de sectores
    Route::get('/sectores', [UserController::class, 'showSectores'])->name('sectores.showSectores');
    Route::get('/sectores/create', [UserController::class, 'createSector'])->name('sectores.create');
    Route::get('/sectores/{sector_codigo}', [UserController::class, 'showSector'])->name('sectores.showSector');
    Route::put('/sectores/{sector_codigo}', [UserController::class, 'updateSector'])->name('sectores.updateSector');
    Route::post('/sectores', [UserController::class, 'storeSector'])->name('sectores.store');
});


// facturacion
Route::middleware([CheckAdminOrRole::class])->group(function () {
    Route::get('/facturacion', [App\Http\Controllers\FacturacionController::class, 'index'])->name('facturacion.index');
    Route::get('/facturacion/listado', [App\Http\Controllers\FacturacionController::class, 'listarFacturas'])->name('facturacion.listado');
    Route::get('/facturacion/detalle/{id}', [App\Http\Controllers\FacturacionController::class, 'verFactura'])->name('facturacion.ver');
    Route::get('/facturacion/{factura}/descargar', [App\Http\Controllers\FacturacionController::class, 'descargar'])->name('facturacion.descargar');
    Route::get('/facturacion/facturar/{cotizacion}', [App\Http\Controllers\FacturacionController::class, 'facturar'])->name('facturacion.show');
    Route::post('/facturacion/facturar/{cotizacion}', [App\Http\Controllers\FacturacionController::class, 'generarFacturaArca'])->name('facturacion.facturar');
});

// ventas
Route::middleware([CheckAdminOrRole::class])->group(function () {
    Route::get('/ventas', [VentasController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/create', [VentasController::class, 'create'])->name('ventas.create');
    Route::post('/ventas', [VentasController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/buscar-para-clonar', [VentasController::class, 'buscarParaClonar'])->name('ventas.buscar-para-clonar');
    Route::get('/ventas/{cotiNum}/obtener-para-clonar', [VentasController::class, 'obtenerParaClonar'])->name('ventas.obtener-para-clonar');
    Route::get('/ventas/{id}/edit', [VentasController::class, 'edit'])->name('ventas.edit');
    Route::get('/ventas/{id}/print', [VentasController::class, 'imprimir'])->name('ventas.print');
    Route::put('/ventas/{id}', [VentasController::class, 'update'])->name('ventas.update');
    Route::delete('/ventas/{id}', [VentasController::class, 'destroy'])->name('ventas.destroy');
    
    // APIs para ventas/cotizaciones
    Route::get('/api/clientes/buscar', [VentasController::class, 'buscarClientes'])->name('api.clientes.buscar');
    Route::get('/api/clientes/{codigo}/empresas-relacionadas', [VentasController::class, 'obtenerEmpresasRelacionadas'])->name('api.clientes.empresas-relacionadas');
    Route::get('/api/clientes/{codigo}', [VentasController::class, 'obtenerCliente'])->name('api.clientes.obtener');
    Route::get('/api/ensayos', [VentasController::class, 'obtenerEnsayos'])->name('api.ensayos');
    Route::get('/api/componentes', [VentasController::class, 'obtenerComponentes'])->name('api.componentes');
    Route::get('/api/metodos-muestreo', [VentasController::class, 'obtenerMetodosMuestreo'])->name('api.metodos-muestreo');
    Route::get('/api/metodos-analisis', [VentasController::class, 'obtenerMetodosAnalisis'])->name('api.metodos-analisis');
    Route::get('/api/leyes-normativas', [VentasController::class, 'obtenerLeyesNormativas'])->name('api.leyes-normativas');
    Route::get('/api/cotizaciones/{cotiNum}/versiones', [VentasController::class, 'obtenerVersiones'])->name('api.cotizaciones.versiones');
    Route::get('/api/cotizaciones/{cotiNum}/versiones/{version}', [VentasController::class, 'cargarVersion'])->name('api.cotizaciones.cargar-version');

    Route::get('/clientes', [ClientesController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/create', [ClientesController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClientesController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{id}/edit', [ClientesController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{id}', [ClientesController::class, 'update'])->name('clientes.update');
    Route::delete('/clientes/{id}', [ClientesController::class, 'destroy'])->name('clientes.destroy');
});

// firmador
Route::middleware([CheckAdminOrRole::class])->group(function () {
    //informes
    Route::get('/informes', [InformeController::class, 'index'])->name('informes.index');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}', [InformeController::class, 'show'])->name('informes.show');
    Route::get('/informes/pdf-masivo/{cotizacion}', [InformeController::class, 'generarPdfMasivo'])->name('informes.pdf-masivo');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}/pdf', [InformeController::class, 'generarPdf'])->name('informes.pdf');
    Route::get('/informes/{cotio_numcoti}/{cotio_item}/{instance_number}/firmar', [InformeController::class, 'firmarInforme'])->name('informes.firmar');
    Route::get('/informes-api/{cotio_numcoti}/{cotio_item}/{instance_number}', [InformeController::class, 'getInformeData'])->name('api.informes.get');
    Route::put('/informes-api/{cotio_numcoti}/{cotio_item}/{instance_number}', [InformeController::class, 'updateInforme'])->name('api.informes.update');
    Route::get('/informes/firma-exitosa', [InformeController::class, 'firmaExitosa'])->name('informes.firma-exitosa');
    Route::get('/informes/firma-error', [InformeController::class, 'firmaError'])->name('informes.firma-error');
    Route::get('/informes/firma-rechazada', [InformeController::class, 'firmaRechazada'])->name('informes.firma-rechazada');
});

    // // Métodos de Muestreo
    // Route::resource('metodos-muestreo', App\Http\Controllers\MetodoMuestreoController::class)->parameters([
    //     'metodos-muestreo' => 'metodoMuestreo'
    // ]);
    // Route::get('metodos-muestreo/{metodoMuestreo}/delete', [App\Http\Controllers\MetodoMuestreoController::class, 'delete'])->name('metodos-muestreo.delete');
    
    // // Métodos de Análisis
    // Route::resource('metodos-analisis', App\Http\Controllers\MetodoAnalisisController::class)->parameters([
    //     'metodos-analisis' => 'metodoAnalisis'
    // ]);
    // Route::get('metodos-analisis/{metodoAnalisis}/delete', [App\Http\Controllers\MetodoAnalisisController::class, 'delete'])->name('metodos-analisis.delete');
    
    // // Leyes y Normativas
    // Route::resource('leyes-normativas', App\Http\Controllers\LeyNormativaController::class)->parameters([
    //     'leyes-normativas' => 'leyNormativa'
    // ]);
    // Nota: Las rutas de leyes-normativas están definidas arriba en la línea 308
    
    // Variables
    Route::resource('variables', App\Http\Controllers\VariableController::class);
    Route::get('variables/{variable}/delete', [App\Http\Controllers\VariableController::class, 'delete'])->name('variables.delete');
    
    // API Routes para variables
    Route::get('variables-api', [App\Http\Controllers\VariableController::class, 'apiIndex'])->name('variables.api.index');
    Route::post('variables-api', [App\Http\Controllers\VariableController::class, 'apiStore'])->name('variables.api.store');
