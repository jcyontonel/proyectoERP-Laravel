<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return EmpresaActual::id($this->user()) !== null;
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());

        $deLaEmpresa = fn (bool $conActivo = true) => fn ($q) => $q
            ->where('empresa_id', $empresa)
            ->when($conActivo, fn ($q) => $q->where('activo', true))
            ->whereNull('deleted_at');

        return [
            'accion'         => ['required', 'in:registrar,borrador'],
            'correlativo_id' => ['required', Rule::exists(Correlativo::class, 'id')->where($deLaEmpresa())],
            'almacen_id'     => ['required', Rule::exists(Almacen::class, 'id')->where($deLaEmpresa())],
            'cliente_id'     => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', $empresa)],
            'fecha_emision'  => ['required', 'date'],
            'metodo_pago'    => ['required', Rule::in(array_keys(config('ventas.metodos_pago')))],
            // Crédito: queda en Cuentas por cobrar (exige cliente; el adelanto es opcional)
            'condicion_pago'    => ['nullable', 'in:contado,credito'],
            'fecha_vencimiento' => ['nullable', 'required_if:condicion_pago,credito', 'date', 'after_or_equal:fecha_emision'],
            'adelanto'          => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cotizacion_id'     => ['nullable', 'integer'],   // venta que viene de una cotización (se valida en CotizacionService)
            'observaciones'  => ['nullable', 'string', 'max:1000'],
            'descuento'      => ['nullable', 'numeric', 'between:0,100'],

            'productos'            => ['required', 'array', 'min:1'],
            'productos.*.id'       => ['required', Rule::exists(Producto::class, 'id')->where($deLaEmpresa())],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'correlativo_id.required' => 'Selecciona el comprobante a emitir.',
            'correlativo_id.exists'   => 'El comprobante seleccionado no está disponible.',
            'almacen_id.required'     => 'Selecciona el almacén de donde sale la mercadería.',
            'almacen_id.exists'       => 'El almacén seleccionado no está disponible.',
            'cliente_id.exists'       => 'El cliente seleccionado no pertenece a tu empresa.',
            'metodo_pago.in'          => 'El método de pago no es válido.',
            'productos.required'      => 'Agrega al menos un producto.',
            'productos.min'           => 'Agrega al menos un producto.',
            'productos.*.id.exists'   => 'Uno de los productos ya no está disponible.',
            'fecha_vencimiento.required_if' => 'Indica hasta cuándo tiene plazo para pagar.',
            'fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser anterior a la emisión.',
        ];
    }
}
