<?php

namespace App\Http\Middleware;

use App\Support\EmpresaActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa actual como "team" de Spatie Permission: así hasRole(), can() y syncRoles()
 * trabajan con los roles del usuario EN ESA empresa (model_has_roles.empresa_id).
 */
class EstablecerEmpresaActual
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            // Desactivado mientras tenía la sesión abierta: se cierra
            if ($user->activo === false) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();

                return redirect()->route('login')->with('error', 'Tu usuario fue desactivado. Consulta con el administrador.');
            }

            setPermissionsTeamId(EmpresaActual::id($user));
        }

        return $next($request);
    }
}
