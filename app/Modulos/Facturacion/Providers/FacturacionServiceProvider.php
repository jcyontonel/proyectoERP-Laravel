<?php

namespace App\Modulos\Facturacion\Providers;

use App\Modulos\Nucleo\Providers\ProveedorModulo;

/** Facturación electrónica: envío a SUNAT, estados, bajas y conexión SUNAT de la empresa */
class FacturacionServiceProvider extends ProveedorModulo
{
    public function boot(): void
    {
        $this->cargarRecursos('facturacion');
        $this->rutasWeb('web.php');
        $this->rutasApi('api.php');
    }
}
