<?php

use App\Http\Controllers\CompraController;
use App\Http\Controllers\ProveedorController;
use Illuminate\Support\Facades\Route;

/* ───── Compras ───── */
Route::middleware('can:gestionar-compras')->group(function () {
    Route::controller(CompraController::class)->prefix('compras')->name('compras.')->group(function () {
        Route::get('exportar', 'export')->name('export');
        Route::patch('{compra}/anular', 'anular')->name('anular');
        Route::post('{compra}/pagos', 'storePago')->name('pagos.store');
    });

    Route::resource('compras', CompraController::class)->only(['index', 'create', 'store', 'show']);
});

/* ───── Proveedores ───── */
Route::resource('proveedores', ProveedorController::class)
    ->except('show')
    ->parameters(['proveedores' => 'proveedor'])
    ->middleware('can:gestionar-proveedores');
