<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'serie' => ['required', 'string', 'max:10'],
            'numero' => ['required', 'string', 'max:20'],
            'fecha_emision' => ['required', 'date'],
            'tipo_comprobante' => ['required', 'string', 'max:50'],
            'moneda' => ['required', 'string', 'max:10'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'total_impuestos' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }
}
