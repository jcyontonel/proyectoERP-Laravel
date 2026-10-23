<?php

use App\Services\SuscripcionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cobros de renovación: genera el cobro pendiente de las suscripciones que vencen pronto.
// (Los estados vencida/suspendida se calculan por fecha: no dependen de esta tarea.)
Artisan::command('suscripciones:procesar', function (SuscripcionService $suscripciones) {
    $n = $suscripciones->procesar();
    $this->info("Cobros de renovación generados: {$n}.");
})->purpose('Genera los cobros pendientes de las suscripciones por vencer');

Schedule::command('suscripciones:procesar')->dailyAt('06:00');

// Facturación electrónica: envía al facturador lo que no llegó (sin conexión), pide bajas pendientes
// y refresca estados por si se perdió un aviso. Requiere "php artisan schedule:work" (o la tarea de Windows).
Artisan::command('facturacion:sincronizar', function (\App\Services\Facturacion\FacturacionElectronicaService $facturacion) {
    if (! $facturacion->activo()) {
        $this->warn('Facturación electrónica apagada: falta FACTURADOR_URL o FACTURADOR_TOKEN en .env.');

        return;
    }
    $r = $facturacion->sincronizar();
    $this->info("Enviados al facturador: {$r['enviados']} · estados refrescados: {$r['consultados']}.");
})->purpose('Reintenta los comprobantes que no llegaron al facturador electrónico');

Schedule::command('facturacion:sincronizar')->everyMinute()->withoutOverlapping(10);
