<?php

namespace App\Modulos\Erp\Http\Requests;

use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear y editar proveedores (también lo usa el alta rápida desde Nueva compra, que responde JSON) */
class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return EmpresaActual::id($this->user()) !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['activo' => $this->has('activo') ? $this->boolean('activo') : true]);
    }

    public function rules(): array
    {
        $empresa = EmpresaActual::id($this->user());
        $ignorar = $this->route('proveedor')?->id;

        // Los índices únicos de la tabla incluyen a los eliminados: por eso no se filtra deleted_at
        $deLaEmpresa = fn () => fn ($q) => $q->where('empresa_id', $empresa);

        return [
            'tipo_documento_id' => ['nullable', 'required_with:numero_documento', Rule::exists(TipoDocumento::class, 'id')],
            'numero_documento'  => [
                'nullable', 'string', 'max:20', 'required_with:tipo_documento_id',
                Rule::unique('proveedores', 'numero_documento')
                    ->where(fn ($q) => $q->where('empresa_id', $empresa)->where('tipo_documento_id', $this->input('tipo_documento_id')))
                    ->ignore($ignorar),
                function (string $atributo, mixed $valor, \Closure $fail) {
                    $codigo = TipoDocumento::whereKey($this->input('tipo_documento_id'))->value('codigo');
                    if ($codigo === '6' && ! preg_match('/^\d{11}$/', (string) $valor)) {
                        $fail('El RUC debe tener 11 dígitos.');
                    }
                    if ($codigo === '1' && ! preg_match('/^\d{8}$/', (string) $valor)) {
                        $fail('El DNI debe tener 8 dígitos.');
                    }
                },
            ],
            'razon_social'     => ['required', 'string', 'max:255', Rule::unique('proveedores', 'razon_social')->where($deLaEmpresa())->ignore($ignorar)],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'contacto_nombre'  => ['nullable', 'string', 'max:255'],
            'contacto_cargo'   => ['nullable', 'string', 'max:255'],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'celular'          => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'pais'             => ['nullable', 'string', 'max:100'],
            'departamento'     => ['nullable', 'string', 'max:100'],
            'provincia'        => ['nullable', 'string', 'max:100'],
            'distrito'         => ['nullable', 'string', 'max:100'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'observacion'      => ['nullable', 'string', 'max:2000'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'razon_social.required'              => 'Ingresa la razón social o el nombre del proveedor.',
            'razon_social.unique'                => 'Ya existe un proveedor con esa razón social (puede estar eliminado).',
            'numero_documento.unique'            => 'Ya existe un proveedor con ese documento (puede estar eliminado).',
            'numero_documento.required_with'     => 'Ingresa el número de documento.',
            'tipo_documento_id.required_with'    => 'Selecciona el tipo de documento.',
        ];
    }
}
