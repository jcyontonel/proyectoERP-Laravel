<?php

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
| Cada módulo publica sus rutas desde app/Modulos/{Modulo}/routes con su provider (ProveedorModulo),
| en el dominio o prefijo de su contexto (config/modulos.php). Aquí solo van rutas que no son de ningún módulo.
| Convenciones (NucleoServiceProvider):
|  - Route::resource con verbos en español: /productos/crear, /productos/{producto}/editar
|  - {producto}, {cliente}, {venta}… solo aceptan números (Route::pattern)
|  - El binding de modelos con empresa_id se limita a la empresa actual (EsDeEmpresa): otra empresa = 404
|  - El permiso de cada grupo se exige con el middleware "can:permiso"
*/
