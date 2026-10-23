<?php

namespace App\Modulos\Nucleo\Http\Controllers\Configuracion;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Nucleo\Http\Requests\Configuracion\UsuarioRequest;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Services\UsuarioService;
use Illuminate\Http\Request;

/** Usuarios con acceso a la empresa actual y su rol en ella */
class UsuarioController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private UsuarioService $usuarios)
    {
    }

    public function index(Request $request)
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'rol' => ['nullable', 'integer'], 'estado' => ['nullable', 'in:activos,inactivos']]);
        $empresa = $this->empresa($request);

        $usuarios = $empresa->usuarios()
            ->with('roles')   // Spatie filtra por el team actual = esta empresa
            ->withCount('empresas')
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($w) => $w->whereLike('name', "%{$f['q']}%")
                ->orWhereLike('apellido', "%{$f['q']}%")->orWhereLike('email', "%{$f['q']}%")))
            ->when(filled($f['rol'] ?? null), fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereKey($f['rol'])))
            ->when(($f['estado'] ?? null) === 'activos', fn ($q) => $q->where('users.activo', true))
            ->when(($f['estado'] ?? null) === 'inactivos', fn ($q) => $q->where('users.activo', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('configuracion.usuarios.index', [
            'usuarios' => $usuarios,
            'roles'    => $this->usuarios->rolesDisponibles($empresa->id)->withCount(['users' => fn ($q) => $q->where('model_has_roles.empresa_id', $empresa->id)])->get(),
            'total'    => $empresa->usuarios()->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('configuracion.usuarios.form', [
            'usuario'       => new User(['activo' => true]),
            'roles'         => $this->usuarios->rolesDisponibles($this->empresaId($request))->get(),
            'rolActual'     => null,
            'editaDatos'    => true,
        ]);
    }

    public function store(UsuarioRequest $request)
    {
        [$user, $existia] = $this->usuarios->crear($this->empresa($request), $request->validated());

        return redirect()->route('usuarios.index')->with('status', $existia
            ? "{$user->nombre_completo} ya tenía cuenta: ahora también tiene acceso a esta empresa."
            : "Usuario {$user->nombre_completo} creado. Ya puede iniciar sesión con {$user->email}.");
    }

    public function edit(Request $request, User $usuario)
    {
        $empresaId = $this->propio($request, $usuario);

        return view('configuracion.usuarios.form', [
            'usuario'    => $usuario,
            'roles'      => $this->usuarios->rolesDisponibles($empresaId)->get(),
            'rolActual'  => $this->usuarios->rolEn($usuario, $empresaId),
            'editaDatos' => $this->usuarios->puedeEditarDatos($usuario, $empresaId),
        ]);
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        $this->propio($request, $usuario);
        $this->usuarios->actualizar($this->empresa($request), $usuario, $request->validated(), $request->user());

        return redirect()->route('usuarios.index')->with('status', "Usuario {$usuario->nombre_completo} actualizado.");
    }

    /** Quita el acceso a esta empresa (no borra la cuenta: puede trabajar en otras) */
    public function destroy(Request $request, User $usuario)
    {
        $this->propio($request, $usuario);
        $this->usuarios->quitar($this->empresa($request), $usuario, $request->user());

        return redirect()->route('usuarios.index')->with('status', "{$usuario->nombre_completo} ya no tiene acceso a esta empresa.");
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function empresa(Request $request): Empresa
    {
        return Empresa::findOrFail($this->empresaId($request));
    }

    /** El usuario debe tener acceso a la empresa actual (si no, 404) */
    private function propio(Request $request, User $usuario): int
    {
        $empresaId = $this->empresaId($request);
        abort_unless($usuario->empresas()->whereKey($empresaId)->exists(), 404);

        return $empresaId;
    }
}
