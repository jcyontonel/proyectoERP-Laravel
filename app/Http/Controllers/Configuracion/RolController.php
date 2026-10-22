<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles de la empresa. Los del sistema (empresa_id NULL: Administrador, Vendedor…) son comunes a
 * todas las empresas y no se modifican; se pueden duplicar como rol propio y ajustar sus permisos.
 */
class RolController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private UsuarioService $usuarios, private AuditoriaService $auditoria)
    {
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);

        return view('configuracion.roles.index', [
            'roles'  => $this->usuarios->rolesDisponibles($empresaId)
                ->with('permissions:id,name')
                ->withCount(['users' => fn ($q) => $q->where('model_has_roles.empresa_id', $empresaId)])
                ->get(),
            'grupos' => $this->grupos(),
        ]);
    }

    /** Nuevo rol; con ?desde={id} parte de los permisos de otro rol */
    public function create(Request $request)
    {
        $base = $request->filled('desde')
            ? $this->usuarios->rolesDisponibles($this->empresaId($request))->with('permissions')->find($request->integer('desde'))
            : null;

        return view('configuracion.roles.form', [
            'rol'      => new Role(['name' => $base ? $base->name . ' (copia)' : '']),
            'marcados' => $base?->permissions->pluck('name')->all() ?? [],
            'grupos'   => $this->grupos(),
            'soloVer'  => false,
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $d = $this->validar($request, $empresaId);

        $rol = DB::transaction(function () use ($d, $empresaId) {
            $rol = Role::create(['name' => $d['name'], 'guard_name' => 'web', 'empresa_id' => $empresaId]);
            $rol->syncPermissions($d['permisos'] ?? []);
            $this->auditoria->registrar('creado', 'Rol', $rol->id, despues: ['nombre' => $rol->name, 'permisos' => $d['permisos'] ?? []]);

            return $rol;
        });

        return redirect()->route('roles.index')->with('status', "Rol «{$rol->name}» creado. Ya puedes asignarlo a tus usuarios.");
    }

    /** Los roles del sistema se muestran en solo lectura */
    public function edit(Request $request, int $rol)
    {
        $rol = $this->rol($request, $rol);

        return view('configuracion.roles.form', [
            'rol'      => $rol,
            'marcados' => $rol->permissions->pluck('name')->all(),
            'grupos'   => $this->grupos(),
            'soloVer'  => $rol->empresa_id === null,
        ]);
    }

    public function update(Request $request, int $rol)
    {
        $rol = $this->rol($request, $rol, propio: true);
        $d = $this->validar($request, $rol->empresa_id, $rol->id);

        $antes = ['nombre' => $rol->name, 'permisos' => $rol->permissions->pluck('name')->sort()->values()->all()];

        DB::transaction(function () use ($rol, $d, $antes) {
            $rol->update(['name' => $d['name']]);
            $rol->syncPermissions($d['permisos'] ?? []);
            $this->auditoria->registrar('permisos', 'Rol', $rol->id, $antes, ['nombre' => $d['name'], 'permisos' => collect($d['permisos'] ?? [])->sort()->values()->all()]);
        });

        return redirect()->route('roles.index')->with('status', "Rol «{$rol->name}» actualizado. Los cambios aplican de inmediato a sus usuarios.");
    }

    public function destroy(Request $request, int $rol)
    {
        $rol = $this->rol($request, $rol, propio: true);

        if (DB::table('model_has_roles')->where('role_id', $rol->id)->exists()) {
            return back()->with('error', "«{$rol->name}» está asignado a usuarios: cámbiales el rol antes de eliminarlo.");
        }

        $rol->delete();
        $this->auditoria->registrar('eliminado', 'Rol', $rol->id, ['nombre' => $rol->name, 'permisos' => $rol->permissions->pluck('name')->all()]);

        return redirect()->route('roles.index')->with('status', "Rol «{$rol->name}» eliminado.");
    }

    /* ───────────────────────── Privado ───────────────────────── */

    /** Rol visible para la empresa; $propio exige que sea de la empresa (no del sistema) */
    private function rol(Request $request, int $id, bool $propio = false): Role
    {
        $rol = $this->usuarios->rolesDisponibles($this->empresaId($request))->with('permissions')->findOrFail($id);
        abort_if($propio && $rol->empresa_id === null, 403, 'Los roles del sistema no se modifican: duplícalo para personalizarlo.');

        return $rol;
    }

    private function validar(Request $request, int $empresaId, ?int $ignorar = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:60',
                // Único entre los roles del sistema y los de la empresa
                Rule::unique('roles', 'name')->ignore($ignorar)->where(fn ($q) => $q->where('guard_name', 'web')
                    ->where(fn ($w) => $w->whereNull('empresa_id')->orWhere('empresa_id', $empresaId)))],
            'permisos'   => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], ['name.unique' => 'Ya existe un rol con ese nombre.'], ['name' => 'nombre']);
    }

    /**
     * Permisos agrupados según config/permisos.php; los que no estén allí van a "Otros".
     *
     * @return Collection<string, array<string, array{0: string, 1: string}>>
     */
    private function grupos(): Collection
    {
        $grupos = collect(config('permisos.grupos'));
        $conocidos = $grupos->flatMap(fn ($p) => array_keys($p))->all();
        $otros = Permission::query()->where('guard_name', 'web')->whereNotIn('name', $conocidos)->orderBy('name')->pluck('name')
            ->mapWithKeys(fn ($n) => [$n => [str($n)->replace(['-', '_'], ' ')->ucfirst()->toString(), '']]);

        return $otros->isEmpty() ? $grupos : $grupos->put('Otros', $otros->all());
    }
}
