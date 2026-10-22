<?php

use App\Http\Controllers\Api\Admin\EmpresaController;
use App\Http\Controllers\Api\Admin\OfertaController;
use App\Http\Controllers\Api\Admin\PagoController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Api\Admin\ResumenController;
use App\Http\Controllers\Api\Admin\SuscripcionController;
use App\Http\Controllers\Api\Admin\UsuarioEmpresaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;
use App\Http\Middleware\TokenAdministracion;
use Illuminate\Support\Facades\Route;

// AuthController@login responde JSON (con token Sanctum) cuando la petición lo pide
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    Route::post('ventas', [VentaController::class, 'store']);
});

/*
| API de administración: la usa el proyecto admin (ricopet/admin) para gestionar empresas,
| planes, ofertas, suscripciones y sus pagos. Token en ERP_ADMIN_TOKEN (Bearer).
*/
Route::prefix('admin')->name('api.admin.')->middleware([TokenAdministracion::class, 'throttle:240,1'])->group(function () {
    Route::get('resumen', ResumenController::class)->name('resumen');
    Route::get('catalogos', [ResumenController::class, 'catalogos'])->name('catalogos');
    Route::get('consulta-ruc/{ruc}', [EmpresaController::class, 'consultaRuc'])->name('consulta-ruc')->where('ruc', '\d{11}');

    Route::apiResource('planes', PlanController::class)->parameters(['planes' => 'plan']);
    Route::apiResource('ofertas', OfertaController::class);

    Route::apiResource('empresas', EmpresaController::class)->except('destroy');
    Route::controller(UsuarioEmpresaController::class)->prefix('empresas/{empresa}/usuarios')->name('empresas.usuarios.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{usuario}', 'update')->name('update');
        Route::put('{usuario}/password', 'password')->name('password');
        Route::delete('{usuario}', 'destroy')->name('destroy');
    });
    Route::post('empresas/{empresa}/suscripcion', [SuscripcionController::class, 'store'])->name('empresas.suscripcion');
    Route::post('empresas/{empresa}/pagos', [PagoController::class, 'store'])->name('empresas.pagos');

    Route::controller(SuscripcionController::class)->prefix('suscripciones/{suscripcion}')->name('suscripciones.')->group(function () {
        Route::put('/', 'update')->name('update');
        Route::post('suspender', 'suspender')->name('suspender');
        Route::post('reactivar', 'reactivar')->name('reactivar');
        Route::post('cancelar', 'cancelar')->name('cancelar');
        Route::post('cobro', 'cobro')->name('cobro');
    });

    Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::post('pagos/{pago}/anular', [PagoController::class, 'anular'])->name('pagos.anular');
});
