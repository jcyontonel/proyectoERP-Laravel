<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear y editar clientes (también el alta rápida desde Nueva venta, que responde JSON) */
class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');

        return $cliente
            ? $this->user()->can('update', $cliente)
            : $this->user()->can('create', Cliente::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_documento' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('numero_documento'))),
            'activo'           => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo_documento_id' => ['required', Rule::exists(TipoDocumento::class, 'id')],
            'numero_documento'  => [
                'required', 'string', 'max:15',
                function (string $atributo, mixed $valor, \Closure $fail) {
                    $codigo = TipoDocumento::whereKey($this->input('tipo_documento_id'))->value('codigo');
                    $valor  = (string) $valor;

                    if ($codigo === '6' && ! preg_match('/^\d{11}$/', $valor)) {
                        $fail('El RUC debe tener 11 dígitos.');
                    } elseif ($codigo === '1' && ! preg_match('/^\d{8}$/', $valor)) {
                        $fail('El DNI debe tener 8 dígitos.');
                    } elseif (in_array($codigo, ['4', '7', 'A'], true) && ! preg_match('/^[A-Z0-9]{5,12}$/', $valor)) {
                        $fail('El documento debe tener entre 5 y 12 letras o números.');
                    }
                },
            ],
            'nombre'    => ['required', 'string', 'max:255'],
            'apellido'  => ['nullable', 'string', 'max:255'],
            'correo'    => ['nullable', 'email', 'max:255'],
            'telefono'  => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documento_id.required' => 'Selecciona el tipo de documento.',
            'numero_documento.required'  => 'Ingresa el número de documento.',
            'nombre.required'            => 'Ingresa el nombre o la razón social.',
        ];
    }
}