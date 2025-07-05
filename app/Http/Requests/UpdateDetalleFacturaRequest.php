<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDetalleFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'factura_id' => 'required|exists:facturas,id',
            'producto_id' => 'required|exists:productos,id',
            'cantidad' => 'required|numeric|min:1',
            'precio_unitario' => 'required|numeric|min:0',
            'impuesto_id' => 'nullable|exists:impuestos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'factura_id.required' => 'La factura es obligatoria.',
            'producto_id.required' => 'El producto es obligatorio.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
        ];
    }
}