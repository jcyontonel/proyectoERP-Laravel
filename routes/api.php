<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

// AuthController@login responde JSON (con token Sanctum) cuando la petición lo pide
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    Route::post('ventas', [VentaController::class, 'store']);
});
