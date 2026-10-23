<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Dashboard\DashboardService;
use App\Services\NotificacionService;
use App\Services\SuscripcionService;
use App\Support\EmpresaActual;
use App\Support\Modulos;
use Illuminate\Support\Facades\Blade;
use App\View\Composers\EmpresaComposer;
use App\View\Composers\LayoutComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Piezas transversales del ERP: servicios por petición, datos del layout, convenciones de rutas y vistas.
 *
 * Nota: el rol Administrador NO usa Gate::before; recibe todos los permisos en RolesYPermisosSeeder.
 * Así las policies siguen verificando que el registro sea de la empresa actual.
 */
class ErpServiceProvider extends ServiceProvider
{
    /** Parámetros de ruta que son ids numéricos (así /ventas/exportar nunca choca con /ventas/{venta}) */
    private const PARAMETROS_ID = [
        'venta', 'cliente', 'compra', 'proveedor', 'producto', 'categoria', 'ajuste', 'transferencia',
        'lote', 'sesion', 'movimiento', 'almacen', 'caja', 'motivo', 'regla', 'fraccionamiento', 'pago',
        'usuario', 'rol', 'serie', 'impuesto', 'unidad',
        'empresa', 'plan', 'oferta', 'suscripcion', 'nota', 'cotizacion', 'orden', 'comprobante',
    ];

    public function register(): void
    {
        // Una instancia por petición: memoriza contadores y la caja abierta mientras se pinta la página
        $this->app->scoped(NotificacionService::class);
        $this->app->scoped(DashboardService::class);
        $this->app->scoped(AuditoriaService::class);   // un request_id por petición
        $this->app->scoped(SuscripcionService::class); // memoriza plan y consumo de la empresa
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        $this->rutas();

        // Pagar a proveedores: quien registra pagos y además compra o lleva la contabilidad (no el vendedor)
        Gate::define('cuentas-por-pagar', fn (User $user) => $user->can('registrar-pagos')
            && ($user->can('gestionar-compras') || $user->can('ver-reportes')));

        Paginator::defaultView('vendor.pagination.ricopet');

        // @ruta('compras.create') … @endruta: solo si el plan incluye ese módulo y la suscripción permite abrirla
        Blade::if('ruta', fn (string $ruta) => Modulos::rutaDisponible($ruta, ($u = auth()->user()) ? EmpresaActual::id($u) : null));

        // Accesos en la auditoría (el intento fallido guarda solo el correo, nunca la contraseña)
        Event::listen(Login::class, fn (Login $e) => app(AuditoriaService::class)->registrar('inicio de sesión', 'User', $e->user->getAuthIdentifier(), userId: $e->user->getAuthIdentifier()));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && app(AuditoriaService::class)->registrar('cierre de sesión', 'User', $e->user->getAuthIdentifier(), userId: $e->user->getAuthIdentifier()));
        Event::listen(Failed::class, fn (Failed $e) => app(AuditoriaService::class)->registrar('acceso fallido', 'User', $e->user?->getAuthIdentifier(), despues: ['email' => $e->credentials['email'] ?? null], empresaId: $e->user ? EmpresaActual::id($e->user) : null, userId: $e->user?->getAuthIdentifier()));

        // Empresa actual en todas las vistas; menú, alertas y caja solo en el layout principal
        View::composer('*', EmpresaComposer::class);
        View::composer('layouts.app', LayoutComposer::class);
    }

    private function rutas(): void
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
