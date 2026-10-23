<?php

namespace App\Modulos\Suscripciones\Providers;

use App\Modulos\Nucleo\Providers\ProveedorModulo;
use App\Modulos\Nucleo\Support\EmpresaActual;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use App\Modulos\Suscripciones\Support\Modulos;
use Illuminate\Support\Facades\Blade;

/** Suscripciones: planes, módulos por plan, límites, pagos y comprobantes de la plataforma */
class SuscripcionesServiceProvider extends ProveedorModulo
{
    public function register(): void
    {
        $this->app->scoped(SuscripcionService::class);   // memoriza plan y consumo de la empresa
    }

    public function boot(): void
    {
        // @ruta('compras.create') … @endruta: solo si el plan incluye ese módulo y la suscripción permite abrirla
        Blade::if('ruta', fn (string $ruta) => Modulos::rutaDisponible($ruta, ($u = auth()->user()) ? EmpresaActual::id($u) : null));

        $this->cargarRecursos('suscripciones');
        $this->rutasWeb('web.php');
        $this->rutasWeb('portal.php', 'suscripciones');
    }
}
