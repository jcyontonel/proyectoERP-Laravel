<?php

namespace App\Modulos\Nucleo\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait EsActivable
{
    public function scopeActivo(Builder $query): void
    {
        $query->where($query->qualifyColumn('activo'), true);
    }
}