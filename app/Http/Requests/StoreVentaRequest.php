<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => 'nullable|exists:clientes,id',
            'empresa_id'      => 'required|exists:empresas,id',
            'serie'           => 'required|string|max:10',
            'fecha_emision'   => 'nullable|date',
            'estado'          => 'nullable|string|max:30',
            'subtotal'        => 'nullable|numeric|min:0',
            'total_impuestos' => 'nullable|numeric|min:0',
            'total'           => 'required|numeric|min:0',
            'observaciones'   => 'nullable|string|max:255',
            'productos'       => 'required|array|min:1',
            'productos.*.id'        => 'required|exists:productos,id',
            'productos.*.cantidad'  => 'required|numeric|min:1',
            'productos.*.precio'    => 'required|numeric|min:0',
            'productos.*.subtotal'  => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.exists' => 'El cliente seleccionado no es válido.',
            'empresa_id.required' => 'La empresa es obligatoria.',
            'empresa_id.exists' => 'La empresa seleccionada no es válida.',
            'serie.required' => 'La serie es obligatoria.',
            'serie.max' => 'La serie no puede tener más de 10 caracteres.',
            'fecha_emision.date' => 'La fecha de emisión no es válida.',
            'estado.max' => 'El estado no puede tener más de 30 caracteres.',
            'subtotal.numeric' => 'El subtotal debe ser un número.',
            'subtotal.min' => 'El subtotal no puede ser negativo.',
            'total_impuestos.numeric' => 'El total de impuestos debe ser un número.',
            'total_impuestos.min' => 'El total de impuestos no puede ser negativo.',
            'total.required' => 'El total de la venta es obligatorio.',
            'total.numeric' => 'El total de la venta debe ser un número.',
            'total.min' => 'El total de la venta no puede ser negativo.',
            'observaciones.max' => 'Las observaciones no pueden tener más de 255 caracteres.',
            'productos.required' => 'Debes agregar al menos un producto.',
            'productos.array' => 'El formato de productos no es válido.',
            'productos.min' => 'Debes agregar al menos un producto.',
            'productos.*.id.required' => 'El producto es obligatorio.',
            'productos.*.id.exists' => 'El producto seleccionado no es válido.',
            'productos.*.cantidad.required' => 'La cantidad es obligatoria.',
            'productos.*.cantidad.numeric' => 'La cantidad debe ser un número.',
            'productos.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'productos.*.precio.required' => 'El precio es obligatorio.',
            'productos.*.precio.numeric' => 'El precio debe ser un número.',
            'productos.*.precio.min' => 'El precio no puede ser negativo.',
            'productos.*.subtotal.required' => 'El subtotal es obligatorio.',
            'productos.*.subtotal.numeric' => 'El subtotal debe ser un número.',
            'productos.*.subtotal.min' => 'El subtotal no puede ser negativo.',
        ];
    }
}
