<?php

namespace App\Modulos\Nucleo\Providers;

use App\Modulos\Nucleo\Services\AuditoriaService;
use App\Modulos\Nucleo\Support\EmpresaActual;
use App\Modulos\Nucleo\View\Composers\EmpresaComposer;
use App\Modulos\Nucleo\View\Composers\LayoutComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * Núcleo: identidad, empresa de trabajo, auditoría, layout y convenciones de rutas comunes a todos los módulos.
 *
 * Nota: el rol Administrador NO usa Gate::before; recibe todos los permisos en RolesYPermisosSeeder.
 * Así las policies siguen verificando que el registro sea de la empresa actual.
 */
class NucleoServiceProvider extends ProveedorModulo
{
    /** Parámetros de ruta que son ids numéricos (así /ventas/exportar nunca choca con /ventas/{venta}) */
    private const PARAMETROS_ID = [
        'venta', 'cliente', 'compra', 'proveedor', 'producto', 'categoria', 'ajuste', 'transferencia',
        'lote', 'sesion', 'movimiento', 'almacen', 'caja', 'motivo', 'regla', 'fraccionamiento', 'pago',
        'usuario', 'rol', 'serie', 'impuesto', 'unidad',
        'empresa', 'plan', 'oferta', 'suscripcion', 'nota', 'cotizacion', 'orden', 'comprobante', 'pedido', 'zona',
    ];

    public function register(): void
    {
        $this->app->scoped(AuditoriaService::class);   // un request_id por petición
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        $this->convencionesDeRutas();

        Paginator::defaultView('vendor.pagination.ricopet');

        // Accesos en la auditoría (el intento fallido guarda solo el correo, nunca la contraseña)
        Event::listen(Login::class, fn (Login $e) => app(AuditoriaService::class)->registrar('inicio de sesión', 'User', $e->user->getAuthIdentifier(), userId: $e->user->getAuthIdentifier()));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && app(AuditoriaService::class)->registrar('cierre de sesión', 'User', $e->user->getAuthIdentifier(), userId: $e->user->getAuthIdentifier()));
        Event::listen(Failed::class, fn (Failed $e) => app(AuditoriaService::class)->registrar('acceso fallido', 'User', $e->user?->getAuthIdentifier(), despues: ['email' => $e->credentials['email'] ?? null], empresaId: $e->user ? EmpresaActual::id($e->user) : null, userId: $e->user?->getAuthIdentifier()));

        // Empresa actual en todas las vistas; menú, alertas y caja solo en el layout principal
        View::composer('*', EmpresaComposer::class);
        View::composer('layouts.app', LayoutComposer::class);

        $this->cargarRecursos('nucleo');
        $this->rutasWeb('acceso.php', autenticado: false);
        $this->rutasWeb('web.php');
        $this->rutasApi('api.php');
    }

    private function convencionesDeRutas(): void
    {
        // Route::resource en español: /productos/crear y /productos/{producto}/editar
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        foreach (self::PARAMETROS_ID as $parametro) {
            Route::pattern($parametro, '[0-9]+');
        }

        // Consultas a RENIEC/SUNAT: cuidan la cuota del plan de APIPeru
        RateLimiter::for('consulta-documento', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
    }
}
