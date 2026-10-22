<?php

namespace App\Http\Requests\Configuracion;

use App\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Datos de la empresa actual (la ruta exige can:gestionar-empresas) */
class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return EmpresaActual::id($this->user()) !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruc'    => preg_replace('/\D/', '', (string) $this->input('ruc')),
            'ubigeo' => preg_replace('/\D/', '', (string) $this->input('ubigeo')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'ruc'                  => ['required', 'digits:11', 'regex:/^(10|15|17|20)\d{9}$/', Rule::unique('empresas', 'ruc')->ignore(EmpresaActual::id($this->user()))],
            'razon_social'         => ['required', 'string', 'max:255'],
            'nombre_comercial'     => ['nullable', 'string', 'max:255'],
            'direccion'            => ['nullable', 'string', 'max:255'],
            'ubigeo'               => ['nullable', 'digits:6'],
            'departamento'         => ['nullable', 'string', 'max:100'],
            'provincia'            => ['nullable', 'string', 'max:100'],
            'distrito'             => ['nullable', 'string', 'max:100'],
            'telefono'             => ['nullable', 'string', 'max:30'],
            'email'                => ['nullable', 'email', 'max:255'],
            'moneda'               => ['required', Rule::exists('monedas', 'codigo')],
            'igv'                  => ['required', 'numeric', 'min:0', 'max:50'],
            'precios_incluyen_igv' => ['boolean'],
            'controlar_stock'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.regex'       => 'El RUC debe empezar con 10, 15, 17 o 20.',
            'ruc.unique'      => 'Ese RUC ya está registrado en otra empresa del sistema.',
        ];
    }
}
