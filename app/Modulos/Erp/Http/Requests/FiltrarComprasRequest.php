<?php

namespace App\Modulos\Erp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros del listado y exportación de compras (GET) */
class FiltrarComprasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'            => ['nullable', 'string', 'max:100'],
            'estado'       => ['nullable', 'in:registrada,anulada'],
            'pago'         => ['nullable', 'in:pendiente,pagada'],
            'proveedor_id' => ['nullable', 'integer'],
            'desde'        => ['nullable', 'date'],
            'hasta'        => ['nullable', 'date', 'after_or_equal:desde'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.'];
    }
}
