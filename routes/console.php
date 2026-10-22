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
