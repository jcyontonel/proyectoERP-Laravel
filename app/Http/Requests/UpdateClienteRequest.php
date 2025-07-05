<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente');
        $empresaId = $this->input('empresa_id'); // o auth()->user()->empresa_id

        return [
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'numero_documento' => [
                'required',
                'string',
                'max:15',
                Rule::unique('clientes', 'numero_documento')
                    ->ignore($clienteId)
                    ->where(fn ($query) => $query->where('empresa_id', $empresaId)),
            ],
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'empresa_id.required' => 'La empresa es obligatoria.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'numero_documento.required' => 'El numero de documento es obligatorio.',
            'numero_documento.unique' => 'Este numero de documento ya está registrado.',
        ];
    }
}