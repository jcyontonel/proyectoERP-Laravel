<?php

namespace App\Modulos\Erp\Http\Requests\Finanzas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cobro de una venta / pago de una compra / abono global de un cliente o proveedor */
class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige el permiso (can:registrar-pagos / can:cuentas-por-pagar)
    }

    public function rules(): array
    {
        $metodos = array_keys(config('ventas.metodos_pago') + config('compras.metodos_pago'));

        return [
            'monto'       => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'metodo_pago' => ['required', Rule::in($metodos)],
            'fecha_pago'  => ['required', 'date', 'before_or_equal:today'],
            'referencia'  => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.min'                  => 'El monto debe ser mayor a cero.',
            'fecha_pago.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
