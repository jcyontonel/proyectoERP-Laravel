<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\{
    EmpresaController,
    ClienteController,
    ProductoController,
    CategoriaController,
    TipoUnidadController,
    FacturaController,
    DetalleFacturaController,
    CorrelativoController,
    ImpuestoController,
    UserController
};

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/perfil', [AuthController::class, 'perfil']);

    // Usuarios (solo accesible a administradores)
    Route::apiResource('/usuarios', UserController::class)
        ->middleware('role:Administrador');

    // Empresas (solo administradores)
    Route::apiResource('/empresas', EmpresaController::class)
        ->middleware('role:Administrador');

    // Clientes (puede ser accedido por Vendedor o superior)
    Route::apiResource('/clientes', ClienteController::class)
        ->middleware('permission:gestionar-clientes');

    // Productos y Categorías (requieren permisos)
    Route::apiResource('/productos', ProductoController::class)
        ->middleware('permission:gestionar-productos');

    Route::apiResource('/categorias', CategoriaController::class)
        ->middleware('permission:gestionar-productos');

    Route::apiResource('/tipos-unidad', TipoUnidadController::class)
        ->middleware('permission:gestionar-productos');

    // Facturas
    Route::apiResource('facturas', FacturaController::class)
        ->middleware('permission:emitir-facturas');

    Route::apiResource('detalle-factura', DetalleFacturaController::class)
        ->only(['store', 'destroy'])
        ->middleware('permission:emitir-facturas');

    // Correlativos e Impuestos (solo administradores o contadores)
    Route::apiResource('correlativos', CorrelativoController::class)
        ->middleware('permission:configurar-correlativos');

    Route::apiResource('impuestos', ImpuestoController::class)
        ->middleware('permission:configurar-impuestos');

    // Rutas específicas
    Route::get('facturas/{factura}/pdf', [FacturaController::class, 'generarPDF']);
});

