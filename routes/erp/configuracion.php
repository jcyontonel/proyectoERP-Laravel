<?php

use App\Http\Controllers\Configuracion\AlmacenController;
use App\Http\Controllers\Configuracion\AuditoriaController;
use App\Http\Controllers\Configuracion\CajaController;
use App\Http\Controllers\Configuracion\ComprobanteSuscripcionController;
use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Configuracion\ImpuestoController;
use App\Http\Controllers\Configuracion\MotivoAjusteController;
use App\Http\Controllers\Configuracion\RolController;
use App\Http\Controllers\Configuracion\SerieController;
use App\Http\Controllers\Configuracion\SunatController;
use App\Http\Controllers\Configuracion\SuscripcionController;
use App\Http\Controllers\Configuracion\UnidadController;
use App\Http\Controllers\Configuracion\UsuarioController;
use Illuminate\Support\Facades\Route;

/* ───── Configuración de la empresa ───── */
Route::prefix('configuracion')->group(function () {
    // Logo de la empresa actual: lo ve cualquier usuario de la empresa (layout, ticket)
    Route::get('empresa/logo', [EmpresaController::class, 'logo'])->name('config.empresa.logo');

    Route::middleware('can:gestionar-empresas')->group(function () {
        Route::controller(EmpresaController::class)->prefix('empresa')->name('config.empresa')->group(function () {
            Route::get('/', 'edit');
            Route::put('/', 'update')->name('.update');
            Route::post('logo', 'subirLogo')->name('.logo.subir');
            Route::delete('logo', 'quitarLogo')->name('.logo.quitar');
        });
        Route::resource('almacenes', AlmacenController::class)->except('show')->parameters(['almacenes' => 'almacen']);
        Route::resource('cajas', CajaController::class)->except('show');
    });

    // Usuarios con acceso a la empresa (destroy = quitar el acceso) y roles propios
    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except('show')->parameters(['usuarios' => 'usuario']);
        Route::resource('roles', RolController::class)->except('show')->parameters(['roles' => 'rol']);
    });

    // Catálogos comunes (impuestos y unidades): se modifica solo lo que no usa otra empresa
    Route::resource('impuestos', ImpuestoController::class)->except('show')->middleware('can:configurar-impuestos');
    Route::resource('unidades', UnidadController::class)->except('show')->parameters(['unidades' => 'unidad'])->middleware('can:gestionar-productos');

    Route::middleware('can:gestionar-empresas')->group(function () {
        Route::get('sunat', [SunatController::class, 'edit'])->name('config.sunat');
        Route::put('sunat', [SunatController::class, 'update'])->name('config.sunat.update');
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('config.auditoria');
        Route::get('suscripcion', SuscripcionController::class)->name('config.suscripcion');
        Route::get('suscripcion/pagos/{pago}/comprobante/{cual}', ComprobanteSuscripcionController::class)
            ->name('config.suscripcion.comprobante')->where('cual', 'impreso|xml|cdr');
        Route::get('auditoria/exportar', [AuditoriaController::class, 'export'])->name('config.auditoria.export');
    });

    Route::resource('series', SerieController::class)
        ->except('show')
        ->parameters(['series' => 'serie'])
        ->middleware('can:configurar-correlativos');

    Route::resource('motivos-ajuste', MotivoAjusteController::class)
        ->except('show')
        ->names('motivos')
        ->parameters(['motivos-ajuste' => 'motivo'])
        ->middleware('can:gestionar-inventario');
});
