<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return EmpresaActual::id($this->user()) !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['afecto_igv' => $this->boolean('afecto_igv')]);   // el checkbox desmarcado no se envía
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());
        $deLaEmpresa = fn ($q) => $q->where('empresa_id', $empresa)->where('activo', true)->whereNull('deleted_at');

        return [
            'proveedor_id'   => ['required', Rule::exists(Proveedor::class, 'id')->where($deLaEmpresa)],
            'almacen_id'     => ['required', Rule::exists(Almacen::class, 'id')->where($deLaEmpresa)],
            'fecha_emision'  => ['required', 'date', 'before_or_equal:today'],
            'fecha_entrega'  => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'condicion_pago' => ['required', 'in:contado,credito'],
            'afecto_igv'     => ['boolean'],
            'observacion'    => ['nullable', 'string', 'max:1000'],

            'productos'            => ['required', 'array', 'min:1'],
            'productos.*.id'       => ['required', 'distinct', Rule::exists(Producto::class, 'id')->where($deLaEmpresa)],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'productos.*.costo'    => ['required', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required'      => 'Selecciona el proveedor.',
            'proveedor_id.exists'        => 'El proveedor seleccionado no está disponible.',
            'almacen_id.required'        => 'Selecciona el almacén que recibirá la mercadería.',
            'fecha_entrega.after_or_equal' => 'La entrega no puede ser antes de la emisión.',
            'productos.required'         => 'Agrega al menos un producto.',
            'productos.min'              => 'Agrega al menos un producto.',
            'productos.*.id.exists'      => 'Uno de los productos ya no está disponible.',
            'productos.*.id.distinct'    => 'Un producto está repetido; suma sus cantidades en una sola línea.',
            'productos.*.costo.required' => 'Indica el costo de cada producto.',
        ];
    }
}
