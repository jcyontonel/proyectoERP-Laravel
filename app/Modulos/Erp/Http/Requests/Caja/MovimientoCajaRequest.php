<?php

namespace App\Modulos\Erp\Http\Requests\Caja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimientoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-caja
    }

    public function rules(): array
    {
        return [
            'tipo'        => ['required', 'in:ingreso,egreso'],
            'concepto'    => ['required', 'string', 'max:150'],
            'metodo_pago' => ['required', Rule::in(array_keys(config('ventas.metodos_pago')))],
            'monto'       => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'concepto.required' => 'Indica el concepto del movimiento (ej. Movilidad, Retiro a banco).',
            'monto.min'         => 'El monto debe ser mayor a cero.',
        ];
    }
}
