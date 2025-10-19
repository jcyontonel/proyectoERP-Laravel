<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexVentaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a hacer esta solicitud.
     */
    public function authorize(): bool
    {
        // Puedes poner lógica de permisos aquí.
        // Por ahora permitimos que cualquier usuario autenticado acceda.
        return auth()->check();
    }

    /**
     * Reglas de validación para el filtro de ventas.
     */
    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Mensajes personalizados de error.
     */
    public function messages(): array
    {
        return [
            'fecha.date' => 'La fecha ingresada no es válida.',
            'fecha.before_or_equal' => 'La fecha no puede ser mayor que la actual.',
        ];
    }
}
