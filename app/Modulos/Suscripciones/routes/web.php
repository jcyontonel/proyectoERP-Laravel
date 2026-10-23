<?php

use App\Modulos\Suscripciones\Http\Controllers\Configuracion\ComprobanteSuscripcionController;
use App\Modulos\Suscripciones\Http\Controllers\Configuracion\SuscripcionController;
use Illuminate\Support\Facades\Route;

/* ───── Mi suscripción (dentro del ERP de la empresa) ───── */
Route::middleware('can:gestionar-empresas')->prefix('configuracion')->group(function () {
    Route::get('suscripcion', SuscripcionController::class)->name('config.suscripcion');
    Route::get('suscripcion/pagos/{pago}/comprobante/{cual}', ComprobanteSuscripcionController::class)
        ->name('config.suscripcion.comprobante')->where('cual', 'impreso|xml|cdr');
});
