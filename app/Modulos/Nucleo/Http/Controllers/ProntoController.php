<?php

namespace App\Modulos\Nucleo\Http\Controllers;

use App\Modulos\Nucleo\Support\Menu;
use Illuminate\Http\Request;

/** Página "Próximamente" para las opciones del menú cuyo módulo aún no existe */
class ProntoController extends Controller
{
    public function __invoke(Request $request, string $modulo)
    {
        $item = Menu::buscar($modulo);
        abort_unless($item, 404);
        abort_if(! empty($item['permiso']) && $request->user()->cannot($item['permiso']), 403);

        return view('pronto', ['item' => $item]);
    }
}
