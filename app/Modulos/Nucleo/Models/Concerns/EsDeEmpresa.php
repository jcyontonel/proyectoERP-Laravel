<?php

namespace App\Modulos\Nucleo\Models\Concerns;

use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Database\Eloquent\Builder;

trait EsDeEmpresa
{
    public function scopeDeEmpresa(Builder $query, int $empresaId): void
    {
        $query->where($query->qualifyColumn('empresa_id'), $empresaId);
    }

    /**
     * Route model binding limitado a la empresa actual: /productos/15 de otra empresa responde 404
     * (sin revelar que existe). Fuera de una petición autenticada (consola, colas) no filtra.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        $user = auth()->user();

        return $user
            ? $query->where($this->qualifyColumn('empresa_id'), EmpresaActual::id($user) ?? 0)
            : $query;
    }
}
