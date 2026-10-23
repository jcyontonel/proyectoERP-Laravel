<?php

use App\Modulos\Erp\Http\Controllers\CompraController;
use App\Modulos\Erp\Http\Controllers\OrdenCompraController;
use App\Modulos\Erp\Http\Controllers\ProveedorController;
use Illuminate\Support\Facades\Route;

/* ───── Compras ───── */
Route::middleware('can:gestionar-compras')->group(function () {
    Route::controller(CompraController::class)->prefix('compras')->name('compras.')->group(function () {
        Route::get('exportar', 'export')->name('export');
        Route::patch('{compra}/anular', 'anular')->name('anular');
        Route::post('{compra}/pagos', 'storePago')->name('pagos.store');
    });

    Route::resource('compras', CompraController::class)->only(['index', 'create', 'store', 'show']);

    /* ───── Órdenes de compra ───── */
    Route::controller(OrdenCompraController::class)->prefix('ordenes-compra')->name('ordenes.')->group(function () {
        Route::post('{orden}/recibir', 'recibir')->name('recibir');
        Route::patch('{orden}/cerrar', 'cerrar')->name('cerrar');
        Route::patch('{orden}/anular', 'anular')->name('anular');
        Route::get('{orden}/imprimir', 'imprimir')->name('imprimir');
    });

    Route::resource('ordenes-compra', OrdenCompraController::class)
        ->except('destroy')
        ->names('ordenes')
        ->parameters(['ordenes-compra' => 'orden']);
});

/* ───── Proveedores ───── */
Route::resource('proveedores', ProveedorController::class)
    ->except('show')
    ->parameters(['proveedores' => 'proveedor'])
    ->middleware('can:gestionar-proveedores');
