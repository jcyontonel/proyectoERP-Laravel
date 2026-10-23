<?php

use App\Modulos\Suscripciones\Services\SuscripcionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas: requieren "php artisan schedule:work" (o schedule:run cada minuto desde el
| Programador de tareas de Windows / cron). Las de cada módulo las registra su provider
| (p. ej. facturacion:procesar en FacturacionServiceProvider).
*/

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

// Trabajos en cola (envíos a SUNAT, correos…): sin un worker aparte, el programador los atiende cada minuto
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')->everyMinute()->withoutOverlapping(5);
