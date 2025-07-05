<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCorrelativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'numero.required' => 'El número es obligatorio.',
        ];
    }
}
