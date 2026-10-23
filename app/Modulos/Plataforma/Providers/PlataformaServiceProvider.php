<?php

namespace App\Modulos\Plataforma\Providers;

use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Providers\ProveedorModulo;
use App\Modulos\Plataforma\Console\AsignarAdmin;
use Illuminate\Support\Facades\Gate;

/**
 * Plataforma: administración global (dueño de la plataforma). Pantallas en el contexto "plataforma" y la API
 * de administración (/api/admin) que también pueden usar clientes externos con su token.
 */
class PlataformaServiceProvider extends ProveedorModulo
{
    public function boot(): void
    {
        // Permiso global: no depende de la empresa actual ni de los roles; se asigna solo por consola
        Gate::define('administrar-plataforma', fn (User $user) => (bool) $user->administra_plataforma && $user->activo);

        if ($this->app->runningInConsole()) {
            $this->commands([AsignarAdmin::class]);
        }

        // Sus vistas solo con el namespace plataforma:: (layouts y componentes propios, sin chocar con los del ERP)
        $this->cargarRecursos('plataforma', global: false);
        $this->rutasWeb('web.php', 'plataforma');
        $this->rutasApi('api.php');
    }
}
