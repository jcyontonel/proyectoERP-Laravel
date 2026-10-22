<?php

use App\Http\Controllers\Caja\MovimientoCajaController;
use App\Http\Controllers\Caja\TurnoCajaController;
use Illuminate\Support\Facades\Route;

/* ───── Caja: turno actual, apertura, cierre, movimientos e historial ───── */
Route::prefix('caja')->name('caja.')->middleware('can:gestionar-caja')->group(function () {
    Route::controller(TurnoCajaController::class)->group(function () {
        Route::get('/', 'actual')->name('index');
        Route::post('abrir', 'abrir')->name('abrir');
        Route::get('cerrar', 'cierre')->name('cierre');
        Route::post('cerrar', 'cerrar')->name('cerrar');

        Route::get('turnos', 'index')->name('turnos.index');
        Route::get('turnos/{sesion}', 'show')->name('turnos.show');
        Route::get('turnos/{sesion}/imprimir', 'imprimir')->name('turnos.imprimir');
    });

    Route::controller(MovimientoCajaController::class)->prefix('movimientos')->name('movimientos.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{movimiento}/anular', 'anular')->name('anular');
    });
});
