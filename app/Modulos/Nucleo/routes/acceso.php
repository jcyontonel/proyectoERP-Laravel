<?php

use App\Modulos\Nucleo\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/* ───── Acceso (sin sesión) ───── */
Route::middleware('guest')->controller(AuthController::class)->group(function () {
    Route::get('login', 'showLogin')->name('login');
    Route::post('login', 'login')->name('login.post');
});
