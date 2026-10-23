<?php

namespace App\Modulos\Plataforma\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege la API de administración:
 *  - Desde fuera: exige el token de ERP_ADMIN_TOKEN como Bearer. Sin token configurado queda cerrada (503).
 *  - Desde las pantallas de Plataforma (ApiAdmin): la petición interna lleva una marca en sus atributos, que un
 *    cliente HTTP no puede poner, y además el usuario con sesión debe administrar la plataforma.
 */
class TokenAdministracion
{
    public const INTERNA = 'plataforma.interna';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get(self::INTERNA) === true) {
            return Gate::allows('administrar-plataforma')
                ? $next($request)
                : response()->json(['message' => 'No administras la plataforma.'], 403);
        }

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
