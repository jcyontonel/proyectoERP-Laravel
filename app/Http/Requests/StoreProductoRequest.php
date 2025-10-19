<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'exists:empresas,id'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'tipo_unidad_id' => ['nullable', 'exists:tipo_unidades,id'],
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                'unique:productos,codigo,NULL,id,empresa_id,' . $this->empresa_id,
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                'unique:productos,nombre,NULL,id,empresa_id,' . $this->empresa_id,
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'es_servicio' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
         return [
            // 📦 Empresa
            'empresa_id.required' => 'Debes seleccionar la empresa a la que pertenece el producto.',
            'empresa_id.exists' => 'La empresa seleccionada no existe o no es válida.',

            // 🏷️ Categoría
            'categoria_id.exists' => 'La categoría seleccionada no existe o no es válida.',

            // ⚖️ Tipo de unidad
            'tipo_unidad_id.exists' => 'El tipo de unidad seleccionado no existe o no es válido.',

            // 🔢 Código
            'codigo.string' => 'El código debe ser un texto válido.',
            'codigo.max' => 'El código no debe superar los 50 caracteres.',
            'codigo.unique' => 'Ya existe un producto con este código en la misma empresa.',

            // 🧾 Nombre
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.string' => 'El nombre debe ser un texto válido.',
            'nombre.max' => 'El nombre no debe superar los 255 caracteres.',
            'nombre.unique' => 'Ya existe un producto con este nombre en la misma empresa.',

            // 📝 Descripción
            'descripcion.string' => 'La descripción debe ser un texto válido.',
            'descripcion.max' => 'La descripción no debe superar los 1000 caracteres.',

            // 💰 Precio unitario
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'precio_unitario.numeric' => 'El precio unitario debe ser un número.',
            'precio_unitario.min' => 'El precio unitario no puede ser negativo.',

            // 📦 Stock
            'stock.numeric' => 'El stock debe ser un número.',
            'stock.min' => 'El stock no puede ser negativo.',

            // ⚙️ Es servicio
            'es_servicio.boolean' => 'El campo "Es servicio" debe ser verdadero o falso.',

            // ✅ Activo
            'activo.boolean' => 'El campo "Activo" debe ser verdadero o falso.',
        ];
    }
}
