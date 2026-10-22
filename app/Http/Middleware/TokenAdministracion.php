<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege la API de administración (proyecto admin): exige el token de ERP_ADMIN_TOKEN como Bearer.
 * Sin token configurado la API queda cerrada (503), nunca abierta.
 */
class TokenAdministracion
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('services.admin.token');

        if ($esperado === '') {
            return response()->json(['message' => 'La API de administración no está configurada (ERP_ADMIN_TOKEN).'], 503);
        }
        if (! hash_equals($esperado, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Token de administración inválido.'], 401);
        }

        return $next($request);
    }
}
