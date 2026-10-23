<?php

namespace App\Services;

use App\Models\Empresa;
use App\Services\AuditoriaService;
use App\Models\User;
use App\Support\EmpresaActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Usuarios de una empresa. El acceso es la fila de empresa_user y el rol vive en model_has_roles
 * con empresa_id (Spatie teams). Reglas:
 *  - Nadie cambia su propio rol, ni se desactiva, ni se quita el acceso.
 *  - Cada empresa conserva al menos un Administrador activo.
 *  - Los datos personales (nombre, correo, contraseña, activo) solo se editan si el usuario trabaja
 *    únicamente en esta empresa: si también está en otra, aquí solo se cambia su rol.
 */
class UsuarioService
{
    /** Roles que la empresa puede asignar: los del sistema y los propios */
    public function rolesDisponibles(int $empresaId): Builder
    {
        return Role::query()
            ->where(fn ($q) => $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId))
            ->orderByRaw('CASE WHEN empresa_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name');
    }

    /** Crea el usuario o, si el correo ya existe, le da acceso a la empresa */
    public function crear(Empresa $empresa, array $d): array
    {
        app(SuscripcionService::class)->exigir($empresa->id, 'usuarios');

        return DB::transaction(function () use ($empresa, $d) {
            $rol = $this->rol($empresa->id, (int) $d['rol_id']);
            $user = User::query()->where('email', $d['email'])->first();
            $existia = (bool) $user;

            if ($user && $user->empresas()->whereKey($empresa->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Ese usuario ya tiene acceso a esta empresa.']);
            }

            if (! $user) {
                if (blank($d['password'] ?? null) || blank($d['name'] ?? null)) {
                    throw ValidationException::withMessages(['password' => 'Para un usuario nuevo indica el nombre y una contraseña.']);
                }
                $user = User::create([
                    'name'     => $d['name'],
                    'apellido' => $d['apellido'] ?? null,
                    'telefono' => $d['telefono'] ?? null,
                    'email'    => $d['email'],
                    'password' => $d['password'],
                    'activo'   => true,
                ]);
            }

            $user->empresas()->attach($empresa->id);
            $this->asignarRol($user, $empresa->id, $rol);

            return [$user, $existia];
        });
    }

    public function actualizar(Empresa $empresa, User $user, array $d, User $editor): User
    {
        return DB::transaction(function () use ($empresa, $user, $d, $editor) {
            $rol = $this->rol($empresa->id, (int) $d['rol_id']);
            $mismo = $user->is($editor);

            if ($this->puedeEditarDatos($user, $empresa->id)) {
                $user->fill(collect($d)->only(['name', 'apellido', 'telefono', 'email'])->all());
                if (filled($d['password'] ?? null)) {
                    $user->password = $d['password'];
                }
                if (! $mismo && array_key_exists('activo', $d)) {
                    $user->activo = (bool) $d['activo'];
                }
                $user->save();
            }

            $rolActual = $this->rolEn($user, $empresa->id);
            if ($rolActual?->id !== $rol->id) {
                if ($mismo) {
                    throw ValidationException::withMessages(['rol_id' => 'No puedes cambiar tu propio rol.']);
                }
                $this->asignarRol($user, $empresa->id, $rol);
            }

            $this->exigirAdministrador($empresa->id);

            return $user;
        });
    }

    /** Quita el acceso a la empresa (el usuario sigue existiendo si trabaja en otras) */
    public function quitar(Empresa $empresa, User $user, User $editor): void
    {
        if ($user->is($editor)) {
            throw ValidationException::withMessages(['usuario' => 'No puedes quitarte el acceso a ti mismo.']);
        }

        DB::transaction(function () use ($empresa, $user) {
            $this->enEmpresa($empresa->id, fn () => $user->syncRoles([]));
            $user->empresas()->detach($empresa->id);
            $this->exigirAdministrador($empresa->id);
            app(AuditoriaService::class)->registrar('acceso quitado', 'User', $user->id, ['email' => $user->email], empresaId: $empresa->id);
        });

        EmpresaActual::olvidar();
    }

    /** ¿Solo trabaja en esta empresa? Entonces sus datos personales se editan desde aquí */
    public function puedeEditarDatos(User $user, int $empresaId): bool
    {
        return ! $user->empresas()->whereKeyNot($empresaId)->exists();
    }

    public function rolEn(User $user, int $empresaId): ?Role
    {
        return $this->enEmpresa($empresaId, fn () => $user->unsetRelation('roles')->roles()->first());
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function rol(int $empresaId, int $rolId): Role
    {
        return $this->rolesDisponibles($empresaId)->whereKey($rolId)->first()
            ?? throw ValidationException::withMessages(['rol_id' => 'Elige un rol válido.']);
    }

    private function asignarRol(User $user, int $empresaId, Role $rol): void
    {
        $anterior = $this->rolEn($user, $empresaId)?->name;
        $this->enEmpresa($empresaId, fn () => $user->unsetRelation('roles')->syncRoles([$rol]));
        app(AuditoriaService::class)->registrar('rol asignado', 'User', $user->id, array_filter(['rol' => $anterior]), ['rol' => $rol->name], $empresaId);
    }

    /** La empresa no puede quedarse sin un Administrador activo */
    public function exigirAdministrador(int $empresaId): void
    {
        $admin = Role::query()->whereNull('empresa_id')->where('name', config('permisos.rol_administrador'))->first();
        if (! $admin) {
            return;
        }

        $quedan = DB::table('model_has_roles as mr')
            ->join('users as u', 'u.id', '=', 'mr.model_id')
            ->join('empresa_user as eu', fn ($j) => $j->on('eu.user_id', '=', 'u.id')->where('eu.empresa_id', $empresaId))
            ->where('mr.role_id', $admin->id)
            ->where('mr.empresa_id', $empresaId)
            ->where('mr.model_type', (new User())->getMorphClass())   // alias de AppServiceProvider::MORPH_MAP
            ->where('u.activo', true)
            ->whereNull('u.deleted_at')
            ->exists();

        if (! $quedan) {
            throw ValidationException::withMessages(['rol_id' => 'La empresa debe conservar al menos un Administrador activo.']);
        }
    }

    /** Ejecuta con el "team" de Spatie fijado en la empresa y luego restaura el anterior */
    private function enEmpresa(int $empresaId, callable $fn): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresaId);

        try {
            return $fn();
        } finally {
            $registrar->setPermissionsTeamId($anterior);
        }
    }
}
