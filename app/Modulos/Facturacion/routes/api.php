<?php

use App\Modulos\Facturacion\Http\Controllers\Api\V1\ComprobanteController;
use App\Modulos\Facturacion\Http\Middleware\AutenticarSistemaExterno;
use Illuminate\Support\Facades\Route;

/*
| API v1 de comprobantes electrónicos para sistemas de fuera de la plataforma (POS, tienda propia…).
| Authorization: Bearer {token del sistema}. Cada token emite por una empresa:
|   php artisan facturacion:sistema {ruc} "Nombre" --webhook=https://...
*/
Route::prefix('v1')->middleware([AutenticarSistemaExterno::class, 'throttle:api'])->name('api.v1.')->group(function () {
    Route::get('ping', [ComprobanteController::class, 'ping'])->name('ping');

    Route::controller(ComprobanteController::class)->prefix('comprobantes')->name('comprobantes.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{id}', 'show')->name('show');
        Route::get('{id}/xml', 'xml')->name('xml');
        Route::get('{id}/cdr', 'cdr')->name('cdr');
        Route::post('{id}/reenviar', 'reenviar')->name('reenviar');
        Route::post('{id}/anular', 'anular')->name('anular');
        Route::post('{id}/consultar', 'consultar')->name('consultar');
    });
});
