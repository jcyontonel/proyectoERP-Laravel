<?php

use App\Modulos\Erp\Http\Controllers\VentaController;
use App\Modulos\Nucleo\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Índice: qué APIs hay (para quien abre /api en el navegador)
Route::get('/', fn () => response()->json([
    'plataforma' => config('app.name'),
    'apis' => [
        ['ruta' => url('api/v1'), 'para' => 'Comprobantes electrónicos de sistemas externos', 'acceso' => 'Bearer token por empresa (php artisan facturacion:sistema)'],
        ['ruta' => url('api/admin'), 'para' => 'Administración de la plataforma (clientes externos)', 'acceso' => 'Bearer ERP_ADMIN_TOKEN'],
        ['ruta' => url('api/tienda/{slug}'), 'para' => 'Datos públicos de una tienda', 'acceso' => 'Público'],
    ],
]))->name('api.indice');

// AuthController@login responde JSON (con token Sanctum) cuando la petición lo pide
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    Route::post('ventas', [VentaController::class, 'store']);
});
