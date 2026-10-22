<?php

use App\Http\Controllers\Inventario\AjusteInventarioController;
use App\Http\Controllers\Inventario\CategoriaController;
use App\Http\Controllers\Inventario\FraccionamientoController;
use App\Http\Controllers\Inventario\KardexController;
use App\Http\Controllers\Inventario\LoteController;
use App\Http\Controllers\Inventario\ReglaFraccionamientoController;
use App\Http\Controllers\Inventario\TransferenciaController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

/* ───── Productos (consulta: cualquier usuario; cambios: ProductoPolicy) ───── */
Route::resource('productos', ProductoController::class);

// Reglas de fraccionamiento del producto de origen (1 saco = 15 kg de otro producto)
Route::controller(ReglaFraccionamientoController::class)->prefix('productos/{producto}/fracciones')->name('productos.fracciones.')->group(function () {
    Route::post('/', 'store')->name('store');
    Route::delete('{regla}', 'destroy')->name('destroy');
});

/* ───── Categorías ───── */
Route::resource('categorias', CategoriaController::class)
    ->except('show')
    ->middleware('can:gestionar-productos');

/* ───── Movimientos de inventario ───── */
Route::middleware('can:gestionar-inventario')->group(function () {
    Route::controller(KardexController::class)->prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('exportar', 'export')->name('export');
    });

    Route::controller(AjusteInventarioController::class)->prefix('ajustes')->name('ajustes.')->group(function () {
        Route::patch('{ajuste}/confirmar', 'confirmar')->name('confirmar');
        Route::patch('{ajuste}/anular', 'anular')->name('anular');
    });
    Route::resource('ajustes', AjusteInventarioController::class)->except('destroy');

    Route::controller(TransferenciaController::class)->prefix('transferencias')->name('transferencias.')->group(function () {
        Route::patch('{transferencia}/enviar', 'enviar')->name('enviar');
        Route::patch('{transferencia}/recibir', 'recibir')->name('recibir');
        Route::patch('{transferencia}/anular', 'anular')->name('anular');
    });
    Route::resource('transferencias', TransferenciaController::class)->except('destroy');

    Route::resource('lotes', LoteController::class)->only(['index', 'show', 'update']);

    // Partir productos: manual (aquí) o automático al vender (VentaService)
    Route::patch('fraccionamientos/{fraccionamiento}/anular', [FraccionamientoController::class, 'anular'])->name('fraccionamientos.anular');
    Route::resource('fraccionamientos', FraccionamientoController::class)->only(['index', 'create', 'store', 'show']);
});
