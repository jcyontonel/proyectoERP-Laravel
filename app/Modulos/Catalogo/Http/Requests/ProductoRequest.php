<?php

namespace App\Modulos\Catalogo\Http\Requests;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Nucleo\Models\Impuesto;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear y editar productos (con sus presentaciones y stock mínimo por almacén) */
class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $producto = $this->route('producto');

        return $producto
            ? $this->user()->can('update', $producto)
            : $this->user()->can('create', Producto::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'es_servicio'  => $this->boolean('es_servicio'),
            'maneja_lotes' => $this->boolean('maneja_lotes'),
            'activo'       => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());
        $ignorar = $this->route('producto')?->id;

        return [
            // El código se usa para escanear: debe ser único entre los productos vigentes de la empresa
            'codigo' => [
                'nullable', 'string', 'max:50',
                Rule::unique('productos', 'codigo')->where(fn ($q) => $q->where('empresa_id', $empresa)->whereNull('deleted_at'))->ignore($ignorar),
            ],
            // El índice único de la tabla (empresa + nombre) incluye a los eliminados
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('productos', 'nombre')->where(fn ($q) => $q->where('empresa_id', $empresa))->ignore($ignorar),
            ],
            'descripcion'     => ['nullable', 'string', 'max:2000'],
            'categoria_id'    => ['nullable', Rule::exists(Categoria::class, 'id')->where(fn ($q) => $q->where('empresa_id', $empresa)->whereNull('deleted_at'))],
            'tipo_unidad_id'  => ['required', Rule::exists(TipoUnidad::class, 'id')],
            'impuesto_id'     => ['nullable', Rule::exists(Impuesto::class, 'id')->where(fn ($q) => $q->where('activo', true))],
            'maneja_lotes'    => ['boolean'],
            'precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'costo_unitario'  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'es_servicio'     => ['boolean'],
            'activo'          => ['boolean'],

            // Presentaciones: 1 [unidad] = [factor] [unidad base]
            'conversiones'                => ['nullable', 'array', 'max:20'],
            'conversiones.*.unidad_id'    => ['required', 'integer', Rule::exists(TipoUnidad::class, 'id'), 'distinct', 'different:tipo_unidad_id'],
            'conversiones.*.factor'       => ['required', 'numeric', 'min:0.0001', 'max:99999999'],
            'conversiones.*.precio_venta' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'conversiones.*.activo'       => ['boolean'],

            // Stock por almacén
            'almacenes'                 => ['nullable', 'array'],
            'almacenes.*.id'            => ['required', Rule::exists(Almacen::class, 'id')->where(fn ($q) => $q->where('empresa_id', $empresa)->whereNull('deleted_at'))],
            'almacenes.*.stock_minimo'  => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'almacenes.*.stock_inicial' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'almacenes.*.stock'         => ['nullable', 'numeric', 'min:0', 'max:99999999'],   // solo sin control de inventario
        ];
    }

    public function attributes(): array
    {
        return [
            'conversiones.*.unidad_id'    => 'unidad de la presentación',
            'conversiones.*.factor'       => 'equivalencia',
            'conversiones.*.precio_venta' => 'precio de la presentación',
            'almacenes.*.stock_minimo'    => 'stock mínimo',
            'almacenes.*.stock_inicial'   => 'stock inicial',
            'tipo_unidad_id'              => 'unidad base',
            'precio_unitario'             => 'precio de venta',
            'costo_unitario'              => 'costo',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique'                       => 'Ya existe un producto con ese nombre (puede estar eliminado).',
            'codigo.unique'                       => 'Ya existe otro producto con ese código.',
            'tipo_unidad_id.required'             => 'Selecciona la unidad base del producto.',
            'conversiones.*.unidad_id.different'  => 'La presentación no puede ser la misma unidad base.',
            'conversiones.*.unidad_id.distinct'   => 'No repitas la misma unidad en las presentaciones.',
            'conversiones.*.factor.min'           => 'La equivalencia debe ser mayor a cero.',
        ];
    }
}