<?php

use App\Modulos\Web\Http\Controllers\PublicaController;
use Illuminate\Support\Facades\Route;

/*
| Tienda pública (sin sesión), en el contexto "web": /tienda/{slug} en desarrollo y ricopet.com/{slug} en
| producción (WEB_DOMINIO y WEB_PREFIJO vacío). Las imágenes se sirven desde aquí (sin storage:link).
*/
Route::controller(PublicaController::class)->where(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->name('tienda.')->group(function () {
    Route::get('/', 'directorio')->name('directorio');   // tiendas publicadas
    Route::get('{slug}', 'pagina')->name('publica');
    Route::get('{slug}/logo', 'logo')->name('logo');
    Route::get('{slug}/imagen/{producto}', 'imagen')->name('imagen')->whereNumber('producto');
});
