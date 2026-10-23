<?php

use App\Http\Controllers\ComprobanteSunatController;
use Illuminate\Support\Facades\Route;

/* ───── Envíos a SUNAT (facturador electrónico) ───── */
Route::middleware('can:emitir-notas')->controller(ComprobanteSunatController::class)->prefix('sunat')->name('sunat.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('resumenes-y-bajas', 'resumenes')->name('resumenes');
});

// Reenviar y descargar: también el vendedor desde el detalle de su venta
Route::middleware('can:ver_ventas')->controller(ComprobanteSunatController::class)->prefix('sunat')->name('sunat.')->group(function () {
    Route::post('{comprobante}/reenviar', 'reenviar')->name('reenviar');
    Route::get('{comprobante}/{cual}', 'archivo')->name('archivo')->where('cual', 'xml|cdr');
});
