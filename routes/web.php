<?php

use App\Modulos\Nucleo\Http\Controllers\AuthController;
use App\Modulos\Nucleo\Http\Controllers\EmpresaActualController;
use App\Modulos\Erp\Http\Controllers\InicioController;
use App\Modulos\Nucleo\Http\Controllers\PerfilController;
use App\Modulos\Nucleo\Http\Controllers\ProntoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
| Cada módulo del ERP vive en routes/erp/*.php y se carga dentro del grupo "auth".
| Convenciones (ver ErpServiceProvider):
|  - Route::resource con verbos en español: /productos/crear, /productos/{producto}/editar
|  - {producto}, {cliente}, {venta}… solo aceptan números (Route::pattern)
|  - El binding de modelos con empresa_id se limita a la empresa actual (EsDeEmpresa): otra empresa = 404
|  - El permiso de cada grupo se exige con el middleware "can:permiso"
*/

Route::middleware('guest')->controller(AuthController::class)->group(function () {
    Route::get('login', 'showLogin')->name('login');
    Route::post('login', 'login')->name('login.post');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', InicioController::class)->name('inicio');
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

    require __DIR__ . '/erp/ventas.php';
    require __DIR__ . '/erp/caja.php';
    require __DIR__ . '/erp/compras.php';
    require __DIR__ . '/erp/finanzas.php';
    require __DIR__ . '/erp/inventario.php';
    require __DIR__ . '/erp/reportes.php';
    require __DIR__ . '/erp/configuracion.php';
    require __DIR__ . '/erp/sunat.php';
});
