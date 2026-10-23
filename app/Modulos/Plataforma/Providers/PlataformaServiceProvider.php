<?php

namespace App\Modulos\Plataforma\Providers;

use App\Modulos\Nucleo\Providers\ProveedorModulo;

/** Plataforma: administración global (dueño de la plataforma). Hoy: la API que usa el panel admin */
class PlataformaServiceProvider extends ProveedorModulo
{
    public function boot(): void
    {
        $this->cargarRecursos('plataforma');
        $this->rutasApi('api.php');
    }
}
