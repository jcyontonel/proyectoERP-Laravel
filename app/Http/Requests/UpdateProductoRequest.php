<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $productoId = $this->route('producto')->id ?? null;

        return [
            'empresa_id' => ['required', 'exists:empresas,id'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'tipo_unidad_id' => ['nullable', 'exists:tipo_unidades,id'],
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                'unique:productos,codigo,' . $productoId . ',id,empresa_id,' . $this->empresa_id,
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                'unique:productos,nombre,' . $productoId . ',id,empresa_id,' . $this->empresa_id,
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
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.unique' => 'Ya existe otro producto con este nombre en la empresa.',
            'codigo.unique' => 'Este código ya está en uso.',
        ];
    }
}
