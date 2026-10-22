<?php

namespace App\Http\Requests\Inventario;

use App\Models\Almacen;
use App\Models\UnidadConversion;
use App\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FraccionamientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-inventario
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());

        return [
            'unidad_conversion_id' => ['required', Rule::exists(UnidadConversion::class, 'id')
                ->where('empresa_id', $empresa)->where(fn ($q) => $q->where('activo', true))->whereNotNull('producto_destino_id')],
            'almacen_id'  => ['required', Rule::exists(Almacen::class, 'id')->where('empresa_id', $empresa)->where(fn ($q) => $q->where('activo', true))->whereNull('deleted_at')],
            'cantidad'    => ['required', 'numeric', 'min:0.0001', 'max:999999'],
            'lote'        => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'unidad_conversion_id.required' => 'Elige qué producto vas a partir.',
            'unidad_conversion_id.exists'   => 'La regla de fraccionamiento no está disponible.',
            'cantidad.min'                  => 'Indica cuántas unidades vas a partir.',
        ];
    }
}
