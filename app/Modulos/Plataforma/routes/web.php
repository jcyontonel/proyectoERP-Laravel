<?php

use App\Modulos\Plataforma\Http\Controllers\Panel\EmpresaController;
use App\Modulos\Plataforma\Http\Controllers\Panel\InicioController;
use App\Modulos\Plataforma\Http\Controllers\Panel\OfertaController;
use App\Modulos\Plataforma\Http\Controllers\Panel\PagoController;
use App\Modulos\Plataforma\Http\Controllers\Panel\PlanController;
use App\Modulos\Plataforma\Http\Controllers\Panel\SuscripcionController;
use App\Modulos\Plataforma\Http\Controllers\Panel\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Plataforma: administración global (empresas, planes, ofertas, suscripciones y pagos de todas las empresas).
| Contexto "plataforma" (config/modulos.php): /plataforma en desarrollo, admin.ricopet.com en producción.
| Solo para usuarios con administra_plataforma (php artisan plataforma:asignar-admin {correo}).
| Las pantallas leen y guardan por la API de administración, dentro de la aplicación (Support\ApiAdmin).
*/
Route::middleware('can:administrar-plataforma')->name('plataforma.')->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    Route::get('empresas/consulta-ruc/{ruc}', [EmpresaController::class, 'consultaRuc'])->name('empresas.consulta-ruc')->where('ruc', '\d{11}');
    Route::resource('empresas', EmpresaController::class)->except('destroy')->whereNumber('empresa');
    Route::prefix('empresas/{empresa}')->whereNumber(['empresa', 'usuario', 'suscripcion'])->group(function () {
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('empresas.usuarios.store');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('empresas.usuarios.update');
        Route::put('usuarios/{usuario}/password', [UsuarioController::class, 'password'])->name('empresas.usuarios.password');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('empresas.usuarios.destroy');
        Route::post('suscripcion', [SuscripcionController::class, 'store'])->name('empresas.suscripcion.store');
        Route::put('suscripcion/{suscripcion}', [SuscripcionController::class, 'update'])->name('empresas.suscripcion.update');
        Route::post('suscripcion/{suscripcion}/suspender', [SuscripcionController::class, 'suspender'])->name('empresas.suscripcion.suspender');
        Route::post('suscripcion/{suscripcion}/reactivar', [SuscripcionController::class, 'reactivar'])->name('empresas.suscripcion.reactivar');
        Route::post('suscripcion/{suscripcion}/cancelar', [SuscripcionController::class, 'cancelar'])->name('empresas.suscripcion.cancelar');
        Route::post('suscripcion/{suscripcion}/cobro', [SuscripcionController::class, 'cobro'])->name('empresas.suscripcion.cobro');
        Route::post('pagos', [PagoController::class, 'store'])->name('empresas.pagos.store');
    });

    Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::get('pagos/exportar', [PagoController::class, 'export'])->name('pagos.export');
    Route::post('pagos/{pago}/anular', [PagoController::class, 'anular'])->name('pagos.anular')->whereNumber('pago');
    Route::post('pagos/{pago}/comprobante', [PagoController::class, 'comprobante'])->name('pagos.comprobante')->whereNumber('pago');
    Route::get('pagos/{pago}/comprobante/{cual}', [PagoController::class, 'archivo'])->name('pagos.comprobante.archivo')
        ->whereNumber('pago')->where('cual', 'impreso|xml|cdr|nota-xml|nota-cdr');

    Route::resource('planes', PlanController::class)->except('show')->parameters(['planes' => 'plan'])->whereNumber('plan');
    Route::resource('ofertas', OfertaController::class)->except('show')->whereNumber('oferta');
});
