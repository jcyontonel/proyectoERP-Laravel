<?php

use App\Modulos\Web\Http\Controllers\PublicaController;
use Illuminate\Support\Facades\Route;

/*
| API pública de la tienda: todo lo de la página en UNA llamada (la app Vue lo pide al cargar) y el envío del pedido.
*/
Route::controller(PublicaController::class)->prefix('tienda/{slug}')->where(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->name('api.tienda.')->group(function () {
    Route::get('/', 'datos')->middleware('throttle:120,1')->name('datos');
    Route::post('pedidos', 'pedido')->middleware('throttle:10,1')->name('pedidos.store');
});
