<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'loginApi']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Illuminate\Http\Request $r) { return $r->user(); });

    Route::post('/ventas', [VentaController::class, 'store']);

    //Route::apiResource('/clientes', ClienteController::class);
});
