<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los filtros de listados (GET): búsqueda, rango de fechas y página.
 * Cada listado agrega sus campos en filtros(). El permiso ya lo exige la ruta (can:…).
 */
abstract class FiltroRequest extends FormRequest
{
    /** @return array<string, mixed> reglas propias del listado */
    abstract protected function filtros(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'     => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'page'  => ['nullable', 'integer', 'min:1'],
        ] + $this->filtros();
    }

    public function messages(): array
    {
        return ['hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".'];
    }
}
