<?php

namespace App\Support;

use App\Http\Middleware\VerificarSuscripcion;
use App\Services\SuscripcionService;
use Illuminate\Support\Str;

/**
 * Módulos del sistema que se reparten entre los planes (config suscripciones.modulos).
 * Una ruta que no pertenece a ningún módulo es de la base: la tienen todos los planes.
 */
class Modulos
{
    /** @return array<string, array{0: string, 1: string, 2: array<int, string>}> */
    public static function catalogo(): array
    {
        return config('suscripciones.modulos', []);
    }

    public static function nombre(string $modulo): string
    {
        return self::catalogo()[$modulo][0] ?? $modulo;
    }

    /** Módulo al que pertenece una ruta (null = base) */
    public static function deRuta(?string $ruta): ?string
    {
        if (! $ruta) {
            return null;
        }
        foreach (self::catalogo() as $clave => [, , $patrones]) {
            if (Str::is($patrones, $ruta)) {
                return $clave;
            }
        }

        return null;
    }

    /** El plan de la empresa incluye el módulo (los de la base siempre) */
    public static function incluido(?string $modulo, ?int $empresaId): bool
    {
        if (! $modulo || ! $empresaId) {
            return true;
        }
        $modulos = app(SuscripcionService::class)->modulos($empresaId);

        return $modulos === null || in_array($modulo, $modulos, true);
    }

    public static function rutaIncluida(?string $ruta, ?int $empresaId): bool
    {
        return self::incluido(self::deRuta($ruta), $empresaId);
    }

    /**
     * La ruta se puede abrir ahora: su módulo está en el plan y, si la suscripción está suspendida,
     * no es una de las páginas bloqueadas (formularios de alta y edición). Lo usan el menú lateral
     * y la directiva @ruta('…') de las vistas.
     */
    public static function rutaDisponible(string $ruta, ?int $empresaId): bool
    {
        if (! self::rutaIncluida($ruta, $empresaId)) {
            return false;
        }
        if ($empresaId && VerificarSuscripcion::bloqueadaSuspendida($ruta)) {
            $s = app(SuscripcionService::class)->vigente($empresaId);

            return ! $s || $s->permiteOperar();
        }

        return true;
    }
}
