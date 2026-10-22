<?php

namespace App\Http\Requests\Inventario;

use App\Models\Almacen;
use App\Models\Producto;
use App\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-inventario
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());
        $deLaEmpresa = fn ($q) => $q->where('empresa_id', $empresa)->where('activo', true)->whereNull('deleted_at');

        return [
            'accion'             => ['required', 'in:borrador,enviar'],
            'almacen_origen_id'  => ['required', Rule::exists(Almacen::class, 'id')->where($deLaEmpresa)],
            'almacen_destino_id' => ['required', 'different:almacen_origen_id', Rule::exists(Almacen::class, 'id')->where($deLaEmpresa)],
            'fecha_movimiento'   => ['required', 'date', 'before_or_equal:today'],
            'observacion'        => ['nullable', 'string', 'max:1000'],

            'productos'            => ['required', 'array', 'min:1', 'max:300'],
            'productos.*.id'       => ['required', Rule::exists(Producto::class, 'id')->where($deLaEmpresa)],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.0001', 'max:99999999'],
            'productos.*.lote'     => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'almacen_destino_id.different' => 'El almacén de destino debe ser distinto al de origen.',
            'productos.required'           => 'Agrega al menos un producto.',
            'productos.*.cantidad.min'     => 'Las cantidades deben ser mayores a cero.',
        ];
    }
}
