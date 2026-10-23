<?php

use App\Modulos\Web\Http\Controllers\PedidoController;
use App\Modulos\Web\Http\Controllers\ProductoWebController;
use App\Modulos\Web\Http\Controllers\TiendaController;
use App\Modulos\Web\Http\Controllers\ZonaEnvioController;
use Illuminate\Support\Facades\Route;

/*
| Página web en el ERP (bloque "Página web" del menú). Sin suscripción web no se entra (módulo web_catalogo);
| pedidos y zonas de envío son del plan Tienda (módulo web_tienda). Ver config suscripciones.funcionalidades.
*/
// Atajo: /web lleva al bloque Página web (si la empresa no tiene plan web, el aviso lo da VerificarSuscripcion)
Route::get('web', fn () => redirect()->route('web.tienda.edit'))->name('web.entrada');

Route::prefix('pagina-web')->name('web.')->group(function () {
    Route::middleware('can:gestionar-empresas')->controller(TiendaController::class)->group(function () {
        Route::get('mi-tienda', 'edit')->name('tienda.edit');
        Route::put('mi-tienda', 'update')->name('tienda.update');
        Route::get('ver', 'ver')->name('tienda.ver');
    });

    Route::middleware('can:gestionar-productos')->controller(ProductoWebController::class)->group(function () {
        Route::get('productos', 'index')->name('productos.index');
        Route::post('productos/masivo', 'masivo')->name('productos.masivo');
        Route::put('productos/{producto}', 'update')->name('productos.update');
    });

    Route::middleware('can:emitir-ventas')->controller(PedidoController::class)->group(function () {
        Route::get('pedidos', 'index')->name('pedidos.index');
        Route::get('pedidos/{pedido}', 'show')->name('pedidos.show');
        Route::patch('pedidos/{pedido}/estado', 'estado')->name('pedidos.estado');
        Route::post('pedidos/{pedido}/convertir', 'convertir')->name('pedidos.convertir');
    });

    Route::middleware('can:gestionar-empresas')->group(function () {
        Route::resource('zonas', ZonaEnvioController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
