<?php

namespace App\Http\Controllers\Concerns;

use App\Support\EmpresaActual;
use Illuminate\Http\Request;

/** Empresa de trabajo del usuario autenticado (403 si no tiene ninguna asignada) */
trait ResuelveEmpresa
{
    protected function empresaId(Request $request): int
    {
        $id = EmpresaActual::id($request->user());
        abort_if(! $id, 403, 'Tu usuario no tiene una empresa asignada.');

        return $id;
    }
}
