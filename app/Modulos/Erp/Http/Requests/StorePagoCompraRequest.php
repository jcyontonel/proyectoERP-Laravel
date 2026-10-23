<?php

namespace App\Modulos\Erp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['required', Rule::in(array_keys(config('compras.metodos_pago')))],
            'referencia'  => ['nullable', 'string', 'max:100'],
            'fecha_pago'  => ['required', 'date', 'before_or_equal:today'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.min'                   => 'El monto debe ser mayor a cero.',
            'fecha_pago.before_or_equal'  => 'La fecha de pago no puede ser futura.',
        ];
    }
}
