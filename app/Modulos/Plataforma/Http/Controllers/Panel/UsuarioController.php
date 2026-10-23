<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;
use Illuminate\Http\Request;

/** Usuarios principales de una empresa del ERP */
class UsuarioController extends Controller
{
    public function __construct(private ApiAdmin $erp)
    {
    }

    public function store(Request $request, int $empresa)
    {
        $r = $this->erp->post("empresas/{$empresa}/usuarios", $request->only(['name', 'apellido', 'email', 'telefono', 'password', 'rol']));

        return $this->volver($empresa, $r['message'] ?? 'Usuario agregado.');
    }

    public function update(Request $request, int $empresa, int $usuario)
    {
        $datos = $request->only(['name', 'apellido', 'email', 'telefono', 'rol']);
        if ($request->has('activo')) {
            $datos['activo'] = $request->boolean('activo');
        }
        $this->erp->put("empresas/{$empresa}/usuarios/{$usuario}", $datos);

        return $this->volver($empresa, 'Usuario actualizado.');
    }

    public function password(Request $request, int $empresa, int $usuario)
    {
        $r = $this->erp->put("empresas/{$empresa}/usuarios/{$usuario}/password", $request->only('password'));

        return $this->volver($empresa, $r['message'] ?? 'Contraseña restablecida.');
    }

    public function destroy(int $empresa, int $usuario)
    {
        $r = $this->erp->delete("empresas/{$empresa}/usuarios/{$usuario}");

        return $this->volver($empresa, $r['message'] ?? 'Acceso quitado.');
    }

    private function volver(int $empresa, string $mensaje)
    {
        return redirect()->to(route('plataforma.empresas.show', $empresa) . '#usuarios')->with('status', $mensaje);
    }
}
