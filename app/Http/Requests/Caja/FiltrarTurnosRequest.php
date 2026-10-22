<?php

namespace App\Http\Requests\Caja;

use App\Http\Requests\FiltroRequest;

class FiltrarTurnosRequest extends FiltroRequest
{
    protected function filtros(): array
    {
        return [
            'caja_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'estado'  => ['nullable', 'in:abierta,cerrada'],
            'tipo'    => ['nullable', 'in:ingreso,egreso'],
        ];
    }
}
