<?php

use App\Http\Controllers\ActividadEconomicaController;
use App\Http\Controllers\CapturaAbonoController;
use App\Http\Controllers\ConvenioPrestamoController;
use App\Http\Controllers\CronogramaController;
use App\Http\Controllers\FiltrarPrestamosController;
use App\Http\Controllers\FiltroPagosController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\MisPagosController;
use App\Http\Controllers\PagarTotalPrestamoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\AsesorController;
use App\Http\Controllers\AvalController;
use App\Http\Controllers\CalculadoraController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CancelarPrestamoController;
use App\Http\Controllers\ClienteAvalesController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\PagoElectronicoController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\ReprogramacionPrestamoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/validate-refresh-token', [AuthController::class, 'validateRefreshToken']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.forgot');

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:admin'])->group(function () { 

    // CRUD CLIENTES
    Route::put('/admin/clientes/{idUsuario}/', [ClientController::class, 'updateClient']);
    Route::patch('/admin/clientes/{idUsuario}/status', [ClientController::class, 'updateStatus']);

    // CRUD EMPLEADOS
    Route::get('/admin/asesores/getasesores', [AsesorController::class, 'getAsesores']);
    Route::post('/admin/asesores', [AsesorController::class, 'createAsesor']);
    Route::put('/admin/asesores/{idUsuario}', [AsesorController::class, 'updateAsesor']);
    Route::patch('/admin/asesores/{idUsuario}/status', [AsesorController::class, 'updateStatusAsesor']);
    Route::get('/admin/asesores/{idUsuario}', [AsesorController::class, 'show']);

    // Rutas para la asignación de avales a clientes
    Route::get('/admin/asignacion/clienteavales', [ClienteAvalesController::class, 'index']);
    Route::post('/admin/asignacion/clienteavales', [ClienteAvalesController::class, 'store']);
    Route::get('/admin/asignacion/clienteavales/{id}', [ClienteAvalesController::class, 'show']);
    Route::delete('/admin/asignacion/clienteavales/{id}', [ClienteAvalesController::class, 'destroy']);
    
    // Rutas para obtener clientes y avales
    Route::get('/admin/asignacion/getclientes', [ClienteAvalesController::class, 'getClientes']);
    Route::get('/admin/asignacion/getavales', [ClienteAvalesController::class, 'getAvales']);

    //Rutas para calculadora

    //Listar clientes en combobox
    Route::get('/admin/calculadora/clientes', [CalculadoraController::class, 'getClients']);
    
    //Listar asesores en combobox
    Route::get('/admin/calculadora/asesores', [CalculadoraController::class, 'getAsesores']);

    //Listar actividades economicas en combobox
    Route::get('/admin/calculadora/actividades/ciiu', [ActividadEconomicaController::class, 'listCiiu']);
    Route::get('/admin/calculadora/actividades/no-sensibles', [ActividadEconomicaController::class, 'listNoSensibles']);

    //Rutas para prestamos
    Route::get('/admin/prestamo', [PrestamoController::class, 'index']);
    Route::post('/admin/prestamo', [PrestamoController::class, 'store']);
    Route::post('/admin/prestamos/grupo', [PrestamoController::class, 'storeGroup']);
    Route::get('/admin/prestamo/{id}', [PrestamoController::class, 'show']);
    Route::post('/admin/grupos', [GrupoController::class, 'store']);

    // Ruta para actualizar estados (normalmente se usaría con un cron job)
    Route::get('/admin/prestamo/actualizar-estados', [PrestamoController::class, 'actualizarEstadosCuotas']);

    //Rutas para Pagar cuotas
    // Búsqueda de clientes
    Route::get('/admin/pagos/buscar-clientes', [PagoController::class,'buscarClientes']);
    // Obtener cuotas pendientes de un cliente
    Route::get('/admin/pagos/cuotas-pendientes/{idCliente}', [PagoController::class,'obtenerCuotasPendientes']);
    // Registrar pago de cuota (parcial o total)
    Route::post('/admin/pagos/cuota', [PagoController::class,'registrarPagoCuota']);
    // Registrar cancelación total del préstamo
    Route::post('/admin/pagos/cancelar-total', [PagarTotalPrestamoController::class, 'registrarCancelacionTotal']);
    // Registrar reprogramacion de  préstamo
    Route::post('/admin/pagos/reprogramar-prestamo', [ReprogramacionPrestamoController::class,'reprogramarPrestamo']);
    // Registrar convenio de  préstamo
    Route::post('/admin/pagos/convenio-prestamo', [ConvenioPrestamoController::class,'registrarConvenio']);

    //Rutas capturar pago y cronograma
    Route::get('/admin/pagos/captura/{idCuota}', [PagoController::class,'obtenerCapturaPago']);

    //Ruta confirmar pago de cliente con captura
    Route::post('/admin/pagos/confirmar-prepago/{idCuota}', [PagoController::class,'confirmarPagoPrepagado']);

     Route::post('/admin/pagos/rechazar-prepago/{idCuota}', [PagoController::class,'rechazarPagoPrepagado']);

    //Rutas para reducor mora
    Route::post('/admin/pagos/reducir-mora', [PagoController::class,'reducirMora']);

    //Rutas captura de bono
    Route::post('/admin/captura-abono/{idUsuario}/{idPrestamo}', [CapturaAbonoController::class, 'uploadCapturaAbono']);
    Route::delete('/admin/captura-abono/{idUsuario}/{idPrestamo}', [CapturaAbonoController::class, 'deleteCapturaAbono']);

    //CRUD PRODUCTOS
    Route::get('/admin/productos', [ProductosController::class, 'index'])->name('productos.index');
    Route::post('/admin/productos', [ProductosController::class, 'store'])->name('productos.store');
    Route::get('/admin/productos/{id}', [ProductosController::class, 'show'])->name('productos.show');
    Route::put('/admin/productos/{id}', [ProductosController::class, 'update'])->name('productos.update');
    Route::delete('/admin/productos/{id}', [ProductosController::class, 'destroy'])->name('productos.destroy');


});

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:cliente'])->group(function () { 

    //Modulo de Estados
    Route::get('/cliente/pagos/cuotas-pendientes', [PagoElectronicoController::class, 'obtenerCuotasPendientes']);
    Route::post('/cliente/pagos/cuota', [PagoElectronicoController::class, 'registrarPagoCuota']);    

    //Modulo de mis Pagos
    Route::get('/cliente/pagos/prestamos-cuotas-pagadas', [MisPagosController::class, 'obtenerPrestamosCuotasPagadas']);
    Route::get('/cliente/pagos/captura-pago/{idCuota}', [MisPagosController::class, 'obtenerCapturaPago']);
    Route::get('/cliente/pagos/comprobante-pago/{idCuota}', [MisPagosController::class, 'obtenerComprobantePago']);
});


// RUTAS PARA ROL ADMIN Y ASESOR
Route::middleware(['auth.jwt', 'CheckRolesMW_ADMIN_ASESOR'])->group(function () { 

   Route::get('/admin/clientes/getclients', [ClientController::class, 'getClients']);
   Route::post('/admin/clientes', [ClientController::class, 'createClient']);

});

// RUTAS PARA ROL ADMIN Y AUDITOR
Route::middleware(['auth.jwt', 'CheckRolesMW_ADMIN_AUDITOR'])->group(function () { 
    
    // Filtrar Pagos Routes
    Route::post('/admin/pagos/filtrar', [FiltroPagosController::class, 'filtrarPagos']);
    Route::get('/admin/cronograma/grupos', [CronogramaController::class, 'getGroups']);
    Route::get('/admin/asesores', [FiltroPagosController::class, 'getAsesores']);

    //Rutas par cronograma
    Route::post('/admin/cronograma/buscar', [CronogramaController::class, 'search']);
    Route::post('/admin/cronograma/buscar-grupo', [CronogramaController::class, 'searchByGroup']);

    //Rutas Filtrar Prestamos
    Route::get('/admin/groups', [FiltrarPrestamosController::class, 'getGroups']);
    Route::get('/admin/advisors', [FiltrarPrestamosController::class, 'getAdvisors']);
    Route::get('/admin/clients', [FiltrarPrestamosController::class, 'getClients']);
    Route::get('/admin/loans', [FiltrarPrestamosController::class, 'getLoans']);
    Route::get('/admin/installments', [FiltrarPrestamosController::class, 'getInstallments']);

    //Rutas comprobante
    Route::get('/admin/pagos/comprobante/{idCuota}', [PagoController::class,'obtenerComprobantePago']);
});

// RUTAS PARA ROL ADMIN Y CLIENTE  Y AUDITOR
Route::middleware(['auth.jwt', 'CheckRolesMW_ADMIN_CLIENTE'])->group(function () { 
    
   Route::get('/admin/captura-abono/{idUsuario}/{idPrestamo}', [CapturaAbonoController::class, 'getCapturaAbono']);

   Route::get('/admin/cronograma/generar/{idPrestamo}', [PrestamoController::class, 'generarPDFCronograma']);

});

// RUTAS PARA VARIOS ROLES
Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () { 


    Route::post('/logout', [AuthController::class, 'logout']);

});
