<?php

use App\Modulos\Erp\Http\Controllers\Configuracion\AlmacenController;
use App\Modulos\Erp\Http\Controllers\Configuracion\CajaController;
use App\Modulos\Erp\Http\Controllers\Configuracion\MotivoAjusteController;
use App\Modulos\Erp\Http\Controllers\Configuracion\SerieController;
use Illuminate\Support\Facades\Route;

/*
| Configuración propia del ERP: almacenes, cajas, series y motivos de ajuste.
| (Empresa, usuarios, roles, impuestos y auditoría: Núcleo · unidades: Catálogo ·
|  conexión SUNAT: Facturación · mi suscripción: Suscripciones)
*/
Route::prefix('configuracion')->group(function () {
    Route::middleware('can:gestionar-empresas')->group(function () {
        Route::resource('almacenes', AlmacenController::class)->except('show')->parameters(['almacenes' => 'almacen']);
        Route::resource('cajas', CajaController::class)->except('show');
    });

    Route::resource('series', SerieController::class)
        ->except('show')
        ->parameters(['series' => 'serie'])
        ->middleware('can:configurar-correlativos');

    Route::resource('motivos-ajuste', MotivoAjusteController::class)
        ->except('show')
        ->names('motivos')
        ->parameters(['motivos-ajuste' => 'motivo'])
        ->middleware('can:gestionar-inventario');
});
