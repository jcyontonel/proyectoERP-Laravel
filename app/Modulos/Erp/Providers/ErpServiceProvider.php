<?php

namespace App\Modulos\Erp\Providers;

use App\Modulos\Erp\Models\CajaSesion;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Policies\CajaSesionPolicy;
use App\Modulos\Erp\Policies\ClientePolicy;
use App\Modulos\Erp\Services\Dashboard\DashboardService;
use App\Modulos\Erp\Services\NotificacionService;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Providers\ProveedorModulo;
use Illuminate\Support\Facades\Gate;

/** ERP: ventas, caja, compras, inventario, finanzas y reportes */
class ErpServiceProvider extends ProveedorModulo
{
    public function register(): void
    {
        // Una instancia por petición: memoriza contadores y la caja abierta mientras se pinta la página
        $this->app->scoped(NotificacionService::class);
        $this->app->scoped(DashboardService::class);
    }

    public function boot(): void
    {
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(CajaSesion::class, CajaSesionPolicy::class);

        // Pagar a proveedores: quien registra pagos y además compra o lleva la contabilidad (no el vendedor)
        Gate::define('cuentas-por-pagar', fn (User $user) => $user->can('registrar-pagos')
            && ($user->can('gestionar-compras') || $user->can('ver-reportes')));

        $this->cargarRecursos('erp');
        $this->rutasWeb('web.php');
    }
}
