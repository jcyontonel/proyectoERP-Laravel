<?php

namespace App\Modulos\Plataforma\Http\Controllers\Api;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Services\EmpresaService;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Nucleo\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Usuarios principales de una empresa (sus administradores y demás accesos) */
class UsuarioEmpresaController extends AdminController
{
    public function __construct(private EmpresaService $empresas, private UsuarioService $usuarios)
    {
    }

    public function index(Empresa $empresa)
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($empresa->id);

        $usuarios = $empresa->usuarios()->with('roles')->withCount('empresas')->orderBy('name')->get()
            ->map(fn (User $u) => $this->datos($u));

        $registrar->setPermissionsTeamId(null);

        return response()->json(['data' => $usuarios, 'roles' => $this->roles()]);
    }

    /** Crea el usuario o da acceso a uno existente (por correo) con un rol del sistema */
    public function store(Request $request, Empresa $empresa, SuscripcionService $suscripciones)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $d = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'rol'      => ['required', Rule::in($this->roles())],
        ]);

        $user = User::query()->where('email', $d['email'])->first();
        if ($user && $user->empresas()->whereKey($empresa->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Ese usuario ya tiene acceso a esta empresa.']);
        }
        $suscripciones->exigir($empresa->id, 'usuarios');

        $existia = (bool) $user;
        DB::transaction(function () use (&$user, $d, $empresa) {
            if (! $user) {
                if (blank($d['password'] ?? null)) {
                    throw ValidationException::withMessages(['password' => 'Para un usuario nuevo indica una contraseña (mínimo 8 caracteres).']);
                }
                $user = User::create(collect($d)->only(['name', 'apellido', 'email', 'telefono', 'password'])->all() + ['activo' => true]);
            }
            $this->empresas->darAcceso($empresa, $user, $d['rol']);
        });

        return response()->json([
            'data'    => $this->enEmpresa($empresa, fn () => $this->datos($user->refresh()->loadCount('empresas'))),
            'message' => $existia ? "{$user->email} ya tenía cuenta: se le dio acceso como {$d['rol']}." : "Usuario {$user->email} creado como {$d['rol']}.",
        ], 201);
    }

    public function update(Request $request, Empresa $empresa, User $usuario)
    {
        $this->propio($empresa, $usuario);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email', $usuario->email)))]);
        $d = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:100'],
            'apellido' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'activo'   => ['sometimes', 'boolean'],
            'rol'      => ['sometimes', 'required', Rule::in($this->roles())],
        ]);

        DB::transaction(function () use ($d, $empresa, $usuario) {
            $usuario->update(collect($d)->except('rol')->all());
            if (isset($d['rol'])) {
                $this->empresas->darAcceso($empresa, $usuario, $d['rol']);
            }
            $this->usuarios->exigirAdministrador($empresa->id);
        });

        return response()->json(['data' => $this->enEmpresa($empresa, fn () => $this->datos($usuario->refresh()->loadCount('empresas')))]);
    }

    /** Nueva contraseña (el usuario la cambia luego desde "Mi perfil") */
    public function password(Request $request, Empresa $empresa, User $usuario)
    {
        $this->propio($empresa, $usuario);
        $d = $request->validate(['password' => ['required', 'string', 'min:8', 'max:100']]);

        $usuario->forceFill(['password' => $d['password'], 'remember_token' => null])->save();

        return response()->json(['message' => "Contraseña de {$usuario->email} restablecida."]);
    }

    /** Quita el acceso a la empresa (la cuenta se conserva) */
    public function destroy(Empresa $empresa, User $usuario)
    {
        $this->propio($empresa, $usuario);

        DB::transaction(function () use ($empresa, $usuario) {
            $this->enEmpresa($empresa, fn () => $usuario->unsetRelation('roles')->syncRoles([]));
            $usuario->empresas()->detach($empresa->id);
            $this->usuarios->exigirAdministrador($empresa->id);
        });

        return response()->json(['message' => "{$usuario->email} ya no tiene acceso a {$empresa->razon_social}."]);
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function propio(Empresa $empresa, User $usuario): void
    {
        abort_unless($usuario->empresas()->whereKey($empresa->id)->exists(), 404);
    }

    /** Roles del sistema (los propios de cada empresa los gestiona la empresa en el ERP) */
    private function roles(): array
    {
        return Role::query()->whereNull('empresa_id')->orderBy('name')->pluck('name')->all();
    }

    private function datos(User $u): array
    {
        return [
            'id'       => $u->id,
            'name'     => $u->name,
            'apellido' => $u->apellido,
            'email'    => $u->email,
            'telefono' => $u->telefono,
            'activo'   => (bool) $u->activo,
            'rol'      => $u->unsetRelation('roles')->getRoleNames()->first(),
            'empresas' => (int) ($u->empresas_count ?? 1),
            'creado'   => $u->created_at?->toIso8601String(),
        ];
    }

    private function enEmpresa(Empresa $empresa, callable $fn): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);
        try {
            return $fn();
        } finally {
            $registrar->setPermissionsTeamId($anterior);
        }
    }
}
