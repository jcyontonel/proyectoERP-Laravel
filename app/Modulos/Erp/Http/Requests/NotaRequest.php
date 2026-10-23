<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Erp\Models\Nota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Emisión de una nota de crédito o débito (la ruta exige can:emitir-notas) */
class NotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipo = $this->input('tipo');

        return [
            'tipo'          => ['required', Rule::in([Nota::CREDITO, Nota::DEBITO])],
            'motivo_codigo' => ['required', Rule::in(array_keys(Nota::MOTIVOS[$tipo] ?? []))],
            'monto'         => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'descripcion'   => ['nullable', 'string', 'max:255'],
            'items'         => ['nullable', 'array'],
            'items.*'       => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'metodo'        => ['required', Rule::in(array_merge(['ninguno'], array_keys(config('ventas.metodos_pago'))))],
            'observacion'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['motivo_codigo.in' => 'Elige un motivo válido para el tipo de nota.'];
    }

    public function attributes(): array
    {
        return ['motivo_codigo' => 'motivo', 'metodo' => 'método', 'items' => 'productos'];
    }
}
