<?php

use App\Modulos\Facturacion\Http\Controllers\Api\FacturadorWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Avisos del facturador electrónico: cambios de estado en SUNAT.
| Sin sesión: se valida la firma HMAC con FACTURADOR_WEBHOOK_SECRETO.
*/
Route::post('facturador/webhook', FacturadorWebhookController::class)
    ->middleware('throttle:600,1')->name('api.facturador.webhook');
