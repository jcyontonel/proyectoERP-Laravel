<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return EmpresaActual::id($this->user()) !== null;
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());

        return [
            'cliente_id'    => ['nullable', Rule::exists('cliente_empresa', 'cliente_id')->where('empresa_id', $empresa)],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
            'vence_el'      => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'descuento'     => ['nullable', 'numeric', 'between:0,100'],
            'condiciones'   => ['nullable', 'string', 'max:2000'],
            'observacion'   => ['nullable', 'string', 'max:1000'],

            'productos'            => ['required', 'array', 'min:1'],
            'productos.*.id'       => ['required', Rule::exists(Producto::class, 'id')->where(fn ($q) => $q->where('empresa_id', $empresa)->where('activo', true)->whereNull('deleted_at'))],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.exists'       => 'El cliente seleccionado no pertenece a tu empresa.',
            'vence_el.after_or_equal' => 'La validez no puede terminar antes de la emisión.',
            'productos.required'      => 'Agrega al menos un producto.',
            'productos.min'           => 'Agrega al menos un producto.',
            'productos.*.id.exists'   => 'Uno de los productos ya no está disponible.',
        ];
    }
}
