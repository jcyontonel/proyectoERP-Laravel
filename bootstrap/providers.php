<?php

// Núcleo primero: define las convenciones de rutas (verbos en español, ids numéricos) que usan los demás módulos
return [
    App\Providers\AppServiceProvider::class,
    App\Modulos\Nucleo\Providers\NucleoServiceProvider::class,
    App\Modulos\Catalogo\Providers\CatalogoServiceProvider::class,
    App\Modulos\Erp\Providers\ErpServiceProvider::class,
    App\Modulos\Facturacion\Providers\FacturacionServiceProvider::class,
    App\Modulos\Plataforma\Providers\PlataformaServiceProvider::class,
    App\Modulos\Web\Providers\WebServiceProvider::class,
];
