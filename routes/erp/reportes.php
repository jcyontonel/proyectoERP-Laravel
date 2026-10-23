<?php

use App\Modulos\Erp\Http\Controllers\ReporteController;
use App\Modulos\Erp\Services\Reportes\ReporteService;
use Illuminate\Support\Facades\Route;

/* ───── Reportes: una ruta con nombre por reporte (reportes.ventas, reportes.utilidad…) ───── */
Route::prefix('reportes')->name('reportes.')->middleware('can:ver-reportes')->controller(ReporteController::class)->group(function () {
    Route::get('/', 'index')->name('index');

    foreach (array_keys(ReporteService::CATALOGO) as $clave) {
        Route::get($clave, 'show')->name($clave)->defaults('reporte', $clave);
        Route::get("{$clave}/exportar", 'exportar')->name("{$clave}.exportar")->defaults('reporte', $clave);
    }
});
