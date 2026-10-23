<?php

namespace App\Modulos\Erp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros del listado y de la exportación de ventas (GET) */
class FiltrarVentasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'       => ['nullable', 'string', 'max:100'],
            'tipo'    => ['nullable', 'string', 'max:30'],
            'estado'  => ['nullable', 'in:completada,borrador,anulada'],
            'desde'   => ['nullable', 'date'],
            'hasta'   => ['nullable', 'date', 'after_or_equal:desde'],
            'formato' => ['nullable', 'in:xlsx,pdf'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'estado.in'            => 'El estado seleccionado no es válido.',
        ];
    }
}
