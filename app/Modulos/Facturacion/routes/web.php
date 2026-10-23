<?php

use App\Modulos\Facturacion\Http\Controllers\ComprobanteSunatController;
use App\Modulos\Facturacion\Http\Controllers\Configuracion\SunatController;
use Illuminate\Support\Facades\Route;

/* ───── Conexión con SUNAT de la empresa (modo, clave SOL y certificado) ───── */
Route::middleware('can:gestionar-empresas')->prefix('configuracion')->group(function () {
    Route::get('sunat', [SunatController::class, 'edit'])->name('config.sunat');
    Route::put('sunat', [SunatController::class, 'update'])->name('config.sunat.update');
});

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
