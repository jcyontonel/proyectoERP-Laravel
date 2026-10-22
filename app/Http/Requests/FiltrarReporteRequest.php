<?php

namespace App\Http\Requests;

/** Filtros comunes de los reportes (cada reporte usa los que declara en ReporteService::CATALOGO) */
class FiltrarReporteRequest extends FiltroRequest
{
    protected function filtros(): array
    {
        return [
            'agrupar'      => ['nullable', 'in:dia,mes'],
            'almacen_id'   => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'user_id'      => ['nullable', 'integer'],
            'dias'         => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
