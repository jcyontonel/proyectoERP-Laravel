<?php

namespace App\Modulos\Nucleo\Http\Controllers;

use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Http\Request;

/** Selector de empresa del layout: cambia la empresa de trabajo en la sesión */
class EmpresaActualController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['empresa_id' => ['required', 'integer']]);

        if (! EmpresaActual::cambiar($request->user(), (int) $data['empresa_id'])) {
            return back()->with('error', 'No tienes acceso a esa empresa.');
        }

        $empresa = EmpresaActual::empresa($request->user());

        // A inicio: la página actual podía ser un registro de la empresa anterior
        return redirect()->route('inicio')->with('status', "Ahora trabajas en {$empresa->nombre_comercial}.");
    }
}
