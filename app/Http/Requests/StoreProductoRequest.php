<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
         return [
            'categoria_id' => 'nullable|exists:categorias,id',
            'tipo_unidad_id' => 'nullable|exists:tipo_unidades,id',
            'impuesto_id' => 'nullable|exists:impuestos,id',
            'codigo' => 'nullable|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio_unitario' => 'required|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'es_servicio' => 'required|boolean',
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'tipo_unidad_id.exists' => 'La unidad de medida seleccionada no existe.',
            'impuesto_id.exists' => 'El impuesto seleccionado no existe.',

            'codigo.max' => 'El código no debe exceder los 255 caracteres.',
            'nombre.required' => 'El nombre del producto o servicio es obligatorio.',
            'nombre.max' => 'El nombre no debe exceder los 255 caracteres.',

            'descripcion.string' => 'La descripción debe ser un texto.',

            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'precio_unitario.numeric' => 'El precio unitario debe ser un número.',
            'precio_unitario.min' => 'El precio unitario no puede ser negativo.',

            'stock.numeric' => 'El stock debe ser un número.',
            'stock.min' => 'El stock no puede ser negativo.',

            'es_servicio.required' => 'Debe indicar si es un servicio.',
            'es_servicio.boolean' => 'El campo "es_servicio" debe ser verdadero o falso.',

            'activo.boolean' => 'El campo "activo" debe ser verdadero o falso.',
        ];
    }
}
