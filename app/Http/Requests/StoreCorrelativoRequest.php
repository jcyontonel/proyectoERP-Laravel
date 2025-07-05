<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrelativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'tipo' => 'required|string|max:10',
            'serie' => 'required|string|max:4|unique:correlativos,serie',
            'numero' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'empresa_id.required' => 'La empresa es obligatoria.',
            'tipo.required' => 'El tipo es obligatorio.',
            'serie.required' => 'La serie es obligatoria.',
            'serie.unique' => 'La serie ya está registrada.',
            'numero.required' => 'El número correlativo es obligatorio.',
        ];
    }
}