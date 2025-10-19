<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'exists:empresas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'empresa_id.required' => 'Debe seleccionar una empresa válida.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.max' => 'El nombre no debe superar los 255 caracteres.',
            'descripcion.string' => 'La descripción debe ser texto.',
            'descripcion.max' => 'La descripción no debe superar los 1000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Si el usuario solo tiene una empresa, asignarla automáticamente
        if (!$this->empresa_id && auth()->check()) {
            $this->merge(['empresa_id' => auth()->user()->empresas->first()->id ?? null]);
        }
    }
}
