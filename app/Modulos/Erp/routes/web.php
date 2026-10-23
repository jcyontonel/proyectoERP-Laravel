<?php

use App\Modulos\Erp\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

/*
| ERP: la gestión diaria del negocio. Cada área en su archivo.
*/
Route::get('/', InicioController::class)->name('inicio');

require __DIR__ . '/ventas.php';
require __DIR__ . '/caja.php';
require __DIR__ . '/compras.php';
require __DIR__ . '/finanzas.php';
require __DIR__ . '/inventario.php';
require __DIR__ . '/reportes.php';
require __DIR__ . '/configuracion.php';
