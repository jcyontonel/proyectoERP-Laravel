<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta (o invitación de un usuario existente por su correo) y edición.
 * Nombre y contraseña son obligatorios solo al crear un usuario nuevo: lo resuelve UsuarioService.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta exige can:gestionar-usuarios
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name'     => [$usuario ? 'required' : 'nullable', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email'    => ['required', 'email', 'max:255', $usuario ? Rule::unique('users', 'email')->ignore($usuario->id) : 'bail'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'rol_id'   => ['required', 'integer'],
            'activo'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'Ese correo ya lo usa otro usuario.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol_id.required'    => 'Elige el rol del usuario.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'password' => 'contraseña', 'rol_id' => 'rol'];
    }
}
