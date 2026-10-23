<?php

namespace App\Modulos\Nucleo\Http\Controllers;

use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Http\Request;

/** Selector de empresa del layout: cambia la empresa de trabajo en la sesión */
class EmpresaActualController extends Controller
{
    public function __invoke(Request $request)
    {
        // ir: página de destino tras cambiar (solo las de esta lista, nunca una URL libre)
        $data = $request->validate(['empresa_id' => ['required', 'integer'], 'ir' => ['nullable', 'in:config.suscripcion']]);

        if (! EmpresaActual::cambiar($request->user(), (int) $data['empresa_id'])) {
            return back()->with('error', 'No tienes acceso a esa empresa.');
        }

        $empresa = EmpresaActual::empresa($request->user());

        // Por defecto a inicio: la página actual podía ser un registro de la empresa anterior
        return redirect()->route($data['ir'] ?? 'inicio')->with('status', "Ahora trabajas en {$empresa->nombre_comercial}.");
    }
}
