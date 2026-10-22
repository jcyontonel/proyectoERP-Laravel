<?php

namespace App\Http\Requests\Caja;

use Illuminate\Foundation\Http\FormRequest;

/** Cierre con arqueo: cantidad por denominación (billetes y monedas) o, si no se detalla, el monto contado */
class CerrarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-caja
    }

    protected function prepareForValidation(): void
    {
        // Las denominaciones vacías se descartan: "0.5" => "", "200" => "1"
        $this->merge([
            'denominaciones' => collect($this->input('denominaciones', []))->filter(fn ($v) => $v !== null && $v !== '')->all(),
        ]);
    }

    public function rules(): array
    {
        $validas = collect(config('caja.denominaciones'))->flatten()->map(fn ($v) => (string) $v)->all();

        return [
            'denominaciones'   => ['array', fn ($attr, $valor, $fail) => collect(array_keys($valor ?? []))
                ->reject(fn ($k) => in_array((string) $k, $validas, true))
                ->whenNotEmpty(fn () => $fail('Hay una denominación no válida en el arqueo.'))],
            'denominaciones.*' => ['integer', 'min:0', 'max:100000'],
            'monto_contado'    => ['nullable', 'required_without:denominaciones', 'numeric', 'min:0', 'max:9999999.99'],
            'observacion'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto_contado.required_without' => 'Cuenta el efectivo del cajón: ingresa las cantidades por denominación o el monto total.',
            'denominaciones.*.integer'       => 'Las cantidades del arqueo deben ser números enteros.',
        ];
    }
}
