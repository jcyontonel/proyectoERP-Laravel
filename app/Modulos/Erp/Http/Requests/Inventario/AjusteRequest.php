<?php

namespace App\Modulos\Erp\Http\Requests\Inventario;

use App\Modulos\Erp\Models\AjusteInventario;
use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\MotivoAjuste;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-inventario
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());
        $deLaEmpresa = fn ($q) => $q->where('empresa_id', $empresa)->where('activo', true)->whereNull('deleted_at');
        $conteo = $this->input('tipo') === 'conteo';

        return [
            'accion'           => ['required', 'in:borrador,confirmar'],
            'tipo'             => ['required', Rule::in(array_keys(AjusteInventario::TIPOS))],
            'almacen_id'       => ['required', Rule::exists(Almacen::class, 'id')->where($deLaEmpresa)],
            'motivo_ajuste_id' => ['required', Rule::exists(MotivoAjuste::class, 'id')->where($deLaEmpresa)],
            'fecha_movimiento' => ['required', 'date', 'before_or_equal:today'],
            'observacion'      => ['nullable', 'string', 'max:1000'],

            'productos'               => ['required', 'array', 'min:1', 'max:300'],
            'productos.*.id'          => ['required', 'distinct', Rule::exists(Producto::class, 'id')->where($deLaEmpresa)],
            // En un conteo es el stock contado (puede ser 0); en ingresos y salidas, la cantidad a mover
            'productos.*.cantidad'    => ['required', 'numeric', $conteo ? 'min:0' : 'min:0.0001', 'max:99999999'],
            'productos.*.lote'        => ['nullable', 'string', 'max:100'],
            'productos.*.vence'       => ['nullable', 'date'],
            'productos.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_ajuste_id.required' => 'Selecciona el motivo del ajuste.',
            'productos.required'        => 'Agrega al menos un producto.',
            'productos.*.id.distinct'   => 'Un producto está repetido: usa una sola línea por producto.',
            'productos.*.cantidad.min'  => 'Las cantidades deben ser mayores a cero.',
            'fecha_movimiento.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
