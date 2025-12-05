<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CompraController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::resource('ventas', VentaController::class);
    Route::resource('compras', CompraController::class);
    Route::resource('productos', ProductoController::class);
    Route::resource('categorias', CategoriaController::class);

    //Route::get('/ventas/create', [VentaController::class, 'create'])->name('ventas.create');
    //Route::post('/ventas/store', [VentaController::class, 'store'])->name('ventas.store');
    //Route::post('/ventas', function () {
    //    return view('dashboard');
    //})->name('ventas.store');
});
