<?php

use App\Modulos\Suscripciones\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Portal de suscripciones (contexto "suscripciones"): /suscripciones en desarrollo, subs.ricopet.com en producción.
*/
Route::get('/', PortalController::class)->name('suscripciones.portal');
