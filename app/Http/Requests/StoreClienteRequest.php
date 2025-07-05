<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'nombre' => 'required|string|max:255',
            'numero_documento' => 'required|string|max:15|unique:clientes,numero_documento',
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
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'numero_documento.required' => 'El numero de documento es obligatorio.',
            'numero_documento.unique' => 'Este numero de documento ya está registrado.',
        ];
    }
}