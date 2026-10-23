<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Erp\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

/** Filtros del listado de clientes (GET) */
class FiltrarClientesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Cliente::class);
    }

    public function rules(): array
    {
        return [
            'q'                 => ['nullable', 'string', 'max:100'],
            'tipo_documento_id' => ['nullable', 'integer'],
            'estado'            => ['nullable', 'in:activos,inactivos'],
            'page'              => ['nullable', 'integer', 'min:1'],
        ];
    }
}
