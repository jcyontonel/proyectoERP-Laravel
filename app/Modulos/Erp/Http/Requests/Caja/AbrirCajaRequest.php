<?php

namespace App\Modulos\Erp\Http\Requests\Caja;

use App\Modulos\Erp\Models\Caja;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbrirCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-caja
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());

        return [
            'caja_id'        => ['required', Rule::exists(Caja::class, 'id')->where('empresa_id', $empresa)->where(fn ($q) => $q->where('activo', true))->whereNull('deleted_at')],
            'monto_apertura' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'observacion'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'caja_id.required'        => 'Selecciona la caja que vas a abrir.',
            'caja_id.exists'          => 'La caja seleccionada no está disponible.',
            'monto_apertura.required' => 'Indica con cuánto efectivo inicias el turno (puede ser 0).',
        ];
    }
}
