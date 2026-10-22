<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/* ───── Ventas ───── */
Route::controller(VentaController::class)->prefix('ventas')->name('ventas.')->group(function () {
    Route::get('exportar', 'export')->name('export')->middleware('can:ver_ventas');
    Route::get('{venta}/ticket', 'ticket')->name('ticket')->middleware('can:ver_ventas');
    Route::patch('{venta}/anular', 'anular')->name('anular')->middleware('can:editar_ventas');
});

Route::resource('ventas', VentaController::class)
    ->only(['index', 'show'])
    ->middleware('can:ver_ventas');

Route::resource('ventas', VentaController::class)
    ->only(['create', 'store', 'edit', 'update'])
    ->middleware('can:emitir-ventas');

/* ───── Clientes (la autorización fina está en ClientePolicy) ───── */
Route::controller(ClienteController::class)->prefix('clientes')->name('clientes.')
    ->middleware('throttle:consulta-documento')
    ->group(function () {
        // DNI (8) o RUC (11): busca y, si no existe, registra con RENIEC/SUNAT (Nueva venta)
        Route::get('documento/{numero}', 'documento')->name('documento')->where('numero', '\d{8}|\d{11}');
        // Solo consulta para autocompletar el formulario de cliente
        Route::get('consulta/{numero}', 'consulta')->name('consulta')->where('numero', '\d{8}|\d{11}');
    });

Route::resource('clientes', ClienteController::class);
