<?php

namespace App\Modulos\Facturacion\Http\Middleware;

use App\Modulos\Facturacion\Models\SistemaExterno;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authorization: Bearer {token del sistema externo}. El sistema queda en $request->attributes('sistema') */
class AutenticarSistemaExterno
{
    public function handle(Request $request, Closure $next): Response
    {
        $sistema = SistemaExterno::porToken($request->bearerToken());
        if (! $sistema) {
            return response()->json(['message' => 'Token inválido o sistema inactivo. Envía Authorization: Bearer {token}.'], 401);
        }

        if (! $sistema->ultimo_uso_at || $sistema->ultimo_uso_at->lt(now()->subMinute())) {
            $sistema->forceFill(['ultimo_uso_at' => now()])->saveQuietly();
        }
        $request->attributes->set('sistema', $sistema);

        return $next($request);
    }
}
