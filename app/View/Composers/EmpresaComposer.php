<?php

namespace App\View\Composers;

use App\Support\EmpresaActual;
use Illuminate\View\View;

/**
 * $empresaActual en todas las vistas (las páginas se pintan antes que el layout).
 * Es barato: EmpresaActual memoriza la consulta durante la petición.
 */
class EmpresaComposer
{
    public function compose(View $view): void
    {
        if (! array_key_exists('empresaActual', $view->getData())) {
            $view->with('empresaActual', auth()->check() ? EmpresaActual::empresa(auth()->user()) : null);
        }
    }
}
