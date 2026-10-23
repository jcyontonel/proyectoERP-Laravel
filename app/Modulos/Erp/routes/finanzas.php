<?php

use App\Modulos\Erp\Http\Controllers\Finanzas\CuentasCobrarController;
use App\Modulos\Erp\Http\Controllers\Finanzas\CuentasPagarController;
use App\Modulos\Erp\Http\Controllers\Finanzas\MovimientoFinanzasController;
use Illuminate\Support\Facades\Route;

/* ───── Finanzas: cuentas por cobrar y por pagar ───── */
Route::prefix('finanzas')->group(function () {
    Route::controller(CuentasCobrarController::class)->prefix('cuentas-por-cobrar')->name('cuentas-cobrar.')
        ->middleware('can:registrar-pagos')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('clientes/{cliente}', 'cuenta')->name('cuenta');
            Route::post('clientes/{cliente}/abonos', 'abonar')->name('abonar');
            Route::post('ventas/{venta}/cobros', 'registrar')->name('registrar');
        });

    // Pagos a proveedores: registrar-pagos y además compras o reportes (Gate en ErpServiceProvider)
    Route::controller(CuentasPagarController::class)->prefix('cuentas-por-pagar')->name('cuentas-pagar.')
        ->middleware('can:cuentas-por-pagar')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('proveedores/{proveedor}', 'cuenta')->name('cuenta');
            Route::post('proveedores/{proveedor}/abonos', 'abonar')->name('abonar');
            Route::post('compras/{compra}/pagos', 'registrar')->name('registrar');
        });

    Route::controller(MovimientoFinanzasController::class)->prefix('movimientos')->name('finanzas.movimientos.')
        ->middleware('can:registrar-pagos')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('exportar', 'export')->name('export');
            Route::patch('{pago}/anular', 'anular')->name('anular');
        });
});
