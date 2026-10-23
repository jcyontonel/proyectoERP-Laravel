<?php

use App\Modulos\Web\Http\Controllers\PublicaController;
use App\Modulos\Web\Models\Tienda;
use Illuminate\Support\Facades\Route;

/*
| Web pública (sin sesión) en el contexto "web": la RAÍZ (localhost/…/public/ en desarrollo, ricopet.com en
| producción). El directorio de tiendas en "/" y cada tienda en "/{slug}". Las imágenes se sirven desde aquí.
| El slug nunca toma las direcciones de los otros módulos (erp, plataforma, api…: Tienda::RESERVADOS).
*/
$slug = '(?!(?:' . implode('|', Tienda::RESERVADOS) . ')$)[a-z0-9]+(?:-[a-z0-9]+)*';

// Enlaces antiguos /tienda y /tienda/{slug}
Route::get('tienda/{viejo?}', fn (?string $viejo = null) => $viejo ? redirect()->route('tienda.publica', $viejo, 301) : redirect()->route('tienda.directorio', [], 301))
    ->where('viejo', '[a-z0-9-]+')->name('tienda.anterior');

Route::controller(PublicaController::class)->where(['slug' => $slug])->name('tienda.')->group(function () {
    Route::get('/', 'directorio')->name('directorio');   // tiendas publicadas
    Route::get('{slug}', 'pagina')->name('publica');
    Route::get('{slug}/logo', 'logo')->name('logo');
    Route::get('{slug}/imagen/{producto}', 'imagen')->name('imagen')->whereNumber('producto');
});
