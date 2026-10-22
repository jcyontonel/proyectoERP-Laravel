<?php

namespace App\Http\Requests;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\TipoUnidad;
use App\Models\Proveedor;
use App\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return EmpresaActual::id($this->user()) !== null;
    }

    protected function prepareForValidation(): void
    {
        // Los checkbox desmarcados no se envían
        $this->merge([
            'afecto_igv'           => $this->boolean('afecto_igv'),
            'precios_incluyen_igv' => $this->boolean('precios_incluyen_igv'),
        ]);
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());

        $deLaEmpresa = fn () => fn ($q) => $q
            ->where('empresa_id', $empresa)
            ->where('activo', true)
            ->whereNull('deleted_at');

        $contado = fn () => $this->input('condicion_pago') === 'contado';

        return [
            'proveedor_id'     => ['required', Rule::exists(Proveedor::class, 'id')->where($deLaEmpresa())],
            'almacen_id'       => ['required', Rule::exists(Almacen::class, 'id')->where($deLaEmpresa())],
            'tipo_comprobante' => ['required', Rule::in(array_keys(config('compras.tipos_comprobante')))],
            'serie'            => ['nullable', 'string', 'max:10', Rule::requiredIf(fn () => $this->input('tipo_comprobante') !== 'otro')],
            'numero'           => ['nullable', 'string', 'max:20', Rule::requiredIf(fn () => $this->input('tipo_comprobante') !== 'otro')],
            'fecha_emision'    => ['required', 'date', 'before_or_equal:today'],
            'moneda'           => ['required', Rule::in(array_keys(config('compras.monedas')))],
            'afecto_igv'           => ['boolean'],
            'precios_incluyen_igv' => ['boolean'],
            'observacion'      => ['nullable', 'string', 'max:1000'],

            'condicion_pago'  => ['required', 'in:contado,credito'],
            'metodo_pago'     => [Rule::requiredIf($contado), 'nullable', Rule::in(array_keys(config('compras.metodos_pago')))],
            'referencia_pago' => ['nullable', 'string', 'max:100'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],

            'productos'            => ['required', 'array', 'min:1'],
            'productos.*.id'       => ['required', Rule::exists(Producto::class, 'id')->where($deLaEmpresa())],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'productos.*.unidad_id' => ['nullable', 'integer', Rule::exists(TipoUnidad::class, 'id')],
            'productos.*.costo'    => ['required', 'numeric', 'min:0', 'max:9999999'],
            'productos.*.precio_venta' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'productos.*.lote'     => ['nullable', 'string', 'max:100', 'required_with:productos.*.vence'],
            'productos.*.vence'    => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required'      => 'Selecciona el proveedor.',
            'proveedor_id.exists'        => 'El proveedor seleccionado no está disponible.',
            'almacen_id.required'        => 'Selecciona el almacén que recibe la mercadería.',
            'serie.required'             => 'Ingresa la serie del comprobante del proveedor.',
            'numero.required'            => 'Ingresa el número del comprobante del proveedor.',
            'fecha_emision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
            'metodo_pago.required'       => 'Selecciona cómo se pagó la compra.',
            'productos.required'         => 'Agrega al menos un producto.',
            'productos.min'              => 'Agrega al menos un producto.',
            'productos.*.id.exists'      => 'Uno de los productos ya no está disponible.',
            'productos.*.costo.required' => 'Indica el costo de cada producto.',
            'productos.*.lote.required_with' => 'Si indicas vencimiento, escribe también el código de lote.',
        ];
    }
}