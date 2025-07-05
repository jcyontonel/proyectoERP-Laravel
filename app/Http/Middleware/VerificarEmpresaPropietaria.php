<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarEmpresaPropietaria
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = auth()->user();
        $recurso = $request->route('recurso'); // el nombre de tu modelo (ej. cliente, factura, etc.)

        if (!$recurso || $recurso->empresa_id !== $usuario->empresa_id) {
            abort(403, 'No tienes acceso a este recurso.');
        }

        return $next($request);
    }
}
