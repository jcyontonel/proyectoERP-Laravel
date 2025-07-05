<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => 'required|exists:clientes,id',
            'correlativo_id' => 'required|exists:correlativos,id',
            'fecha_emision' => 'required|date',
            'moneda' => 'required|string|max:3',
            'total' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'correlativo_id.required' => 'El correlativo es obligatorio.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'moneda.required' => 'La moneda es obligatoria.',
            'total.required' => 'El total es obligatorio.',
        ];
    }
}