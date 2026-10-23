<?php

use App\Modulos\Catalogo\Http\Controllers\Configuracion\UnidadController;
use App\Modulos\Catalogo\Http\Controllers\Inventario\CategoriaController;
use App\Modulos\Catalogo\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

/* ───── Productos (consulta: cualquier usuario; cambios: ProductoPolicy) ───── */
Route::resource('productos', ProductoController::class);

/* ───── Categorías ───── */
Route::resource('categorias', CategoriaController::class)
    ->except('show')
    ->middleware('can:gestionar-productos');

/* ───── Unidades de medida (catálogo común: se modifica solo lo que no usa otra empresa) ───── */
Route::prefix('configuracion')->group(function () {
    Route::resource('unidades', UnidadController::class)->except('show')->parameters(['unidades' => 'unidad'])->middleware('can:gestionar-productos');
});
