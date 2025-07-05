<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImpuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'porcentaje' => 'required|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del impuesto es obligatorio.',
            'porcentaje.required' => 'El porcentaje del impuesto es obligatorio.',
            'porcentaje.numeric' => 'El porcentaje debe ser un valor numérico.',
        ];
    }
}