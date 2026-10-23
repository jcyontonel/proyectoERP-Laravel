<?php

use App\Modulos\Nucleo\Http\Controllers\AuthController;
use App\Modulos\Nucleo\Http\Controllers\Configuracion\AuditoriaController;
use App\Modulos\Nucleo\Http\Controllers\Configuracion\EmpresaController;
use App\Modulos\Nucleo\Http\Controllers\Configuracion\ImpuestoController;
use App\Modulos\Nucleo\Http\Controllers\Configuracion\RolController;
use App\Modulos\Nucleo\Http\Controllers\Configuracion\UsuarioController;
use App\Modulos\Nucleo\Http\Controllers\EmpresaActualController;
use App\Modulos\Nucleo\Http\Controllers\PerfilController;
use App\Modulos\Nucleo\Http\Controllers\ProntoController;
use Illuminate\Support\Facades\Route;

/*
| Núcleo: sesión, empresa de trabajo, mi cuenta, datos de la empresa, usuarios, roles y auditoría.
| Convenciones (NucleoServiceProvider): Route::resource en español (/crear, /editar), parámetros
| numéricos, binding limitado a la empresa actual (EsDeEmpresa) y permisos con "can:permiso".
*/
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
Route::post('empresa-actual', EmpresaActualController::class)->name('empresa.cambiar');

// Mi cuenta (cualquier usuario)
Route::controller(PerfilController::class)->prefix('mi-cuenta')->name('perfil.')->group(function () {
    Route::get('/', 'edit')->name('edit');
    Route::put('/', 'update')->name('update');
    Route::put('contrasena', 'password')->name('password');
    Route::get('preferencias', 'preferencias')->name('preferencias');
    Route::put('preferencias', 'guardarPreferencias')->name('preferencias.guardar');
});

// Opciones del menú cuyo módulo aún no existe (config/menu.php)
Route::get('modulos/{modulo}', ProntoController::class)->name('pronto');

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
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('config.auditoria');
        Route::get('auditoria/exportar', [AuditoriaController::class, 'export'])->name('config.auditoria.export');
    });

    // Usuarios con acceso a la empresa (destroy = quitar el acceso) y roles propios
    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except('show')->parameters(['usuarios' => 'usuario']);
        Route::resource('roles', RolController::class)->except('show')->parameters(['roles' => 'rol']);
    });

    // Catálogo común de impuestos: se modifica solo lo que no usa otra empresa
    Route::resource('impuestos', ImpuestoController::class)->except('show')->middleware('can:configurar-impuestos');
});
