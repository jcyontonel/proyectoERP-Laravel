<?php

namespace App\Http\Requests\Inventario;

use App\Http\Requests\FiltroRequest;

/** Filtros de kardex, ajustes, transferencias y lotes */
class FiltrarInventarioRequest extends FiltroRequest
{
    protected function filtros(): array
    {
        return [
            'almacen_id'  => ['nullable', 'integer'],
            'producto_id' => ['nullable', 'integer'],
            'estado'      => ['nullable', 'string', 'max:20'],
            'tipo'        => ['nullable', 'string', 'max:30'],
            'vencimiento' => ['nullable', 'in:vencidos,por_vencer,vigentes,sin_fecha'],
            'con_stock'   => ['nullable', 'boolean'],
        ];
    }
}
