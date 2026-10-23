<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;

/** Panel: empresas por estado, ingresos, MRR y vencimientos próximos */
class InicioController extends Controller
{
    public function __invoke(ApiAdmin $erp)
    {
        return view('plataforma::inicio', ['r' => $erp->get('resumen')['data']]);
    }
}
