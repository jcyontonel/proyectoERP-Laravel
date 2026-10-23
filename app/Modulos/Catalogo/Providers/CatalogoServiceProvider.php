<?php

namespace App\Modulos\Catalogo\Providers;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Policies\ProductoPolicy;
use App\Modulos\Nucleo\Providers\ProveedorModulo;
use Illuminate\Support\Facades\Gate;

/** Catálogo: productos, categorías y unidades (lo comparten el ERP y la tienda web) */
class CatalogoServiceProvider extends ProveedorModulo
{
    public function boot(): void
    {
        Gate::policy(Producto::class, ProductoPolicy::class);

        $this->cargarRecursos('catalogo');
        $this->rutasWeb('web.php');
    }
}
