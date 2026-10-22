<?php

namespace App\Http\Controllers;

use App\Models\PreferenciaUsuario;
use App\Services\NotificacionService;
use App\Support\EmpresaActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Mi cuenta: datos personales, contraseña y preferencias del usuario autenticado */
class PerfilController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('perfil.edit', [
            'user'     => $user,
            'empresas' => EmpresaActual::disponibles($user),
            'roles'    => EmpresaActual::disponibles($user)->mapWithKeys(function ($e) use ($user) {
                setPermissionsTeamId($e->id);
                $rol = $user->unsetRelation('roles')->getRoleNames()->first();

                return [$e->id => $rol];
            })->tap(fn () => setPermissionsTeamId(EmpresaActual::id($user))),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $d = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], ['email.unique' => 'Ese correo ya lo usa otro usuario.'], ['name' => 'nombres']);

        $user->update($d);

        return redirect()->route('perfil.edit')->with('status', 'Tus datos se actualizaron.');
    }

    /** Cambio de contraseña: pide la actual y cierra las otras sesiones abiertas */
    public function password(Request $request)
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password'        => ['required', 'confirmed', 'different:password_actual', Password::min(8)->letters()->numbers()],
        ], [
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.different'               => 'La nueva contraseña debe ser distinta de la actual.',
            'password.confirmed'               => 'Las contraseñas no coinciden.',
        ], ['password' => 'nueva contraseña']);

        $request->user()->update(['password' => $request->input('password')]);
        Auth::logoutOtherDevices($request->input('password'));

        return redirect()->route('perfil.edit')->with('status', 'Contraseña actualizada. Se cerraron tus sesiones en otros equipos.');
    }

    public function preferencias(Request $request)
    {
        $user = $request->user();

        return view('perfil.preferencias', [
            'pref'     => $user->preferencias(),
            'empresas' => EmpresaActual::disponibles($user),
            'alertas'  => NotificacionService::ALERTAS,
        ]);
    }

    public function guardarPreferencias(Request $request)
    {
        $user = $request->user();
        $d = $request->validate([
            'empresa_inicio_id'  => ['nullable', 'integer', Rule::in(EmpresaActual::disponibles($user)->pluck('id')->all())],
            'ticket_ancho'       => ['required', 'in:58,80'],
            'imprimir_al_vender' => ['boolean'],
            'alertas'            => ['nullable', 'array'],
            'alertas.*'          => ['string', Rule::in(array_keys(NotificacionService::ALERTAS))],
        ]);

        PreferenciaUsuario::updateOrCreate(['user_id' => $user->id], [
            'empresa_inicio_id'  => $d['empresa_inicio_id'] ?? null,
            'ticket_ancho'       => $d['ticket_ancho'],
            'imprimir_al_vender' => $request->boolean('imprimir_al_vender'),
            // El formulario marca las alertas que QUIERE ver: se guardan las ocultas
            'alertas_ocultas'    => array_values(array_diff(array_keys(NotificacionService::ALERTAS), $d['alertas'] ?? [])),
        ]);

        return redirect()->route('perfil.preferencias')->with('status', 'Preferencias guardadas.');
    }
}
