<?php

namespace App\Modulos\Plataforma\Support;

use App\Modulos\Plataforma\Http\Middleware\VerificarSuscripcion;
use App\Modulos\Plataforma\Models\Suscripcion;
use App\Modulos\Plataforma\Services\SuscripcionService;
use Illuminate\Support\Str;

/**
 * Funcionalidades de la plataforma por ruta (config suscripciones.funcionalidades). Una empresa tiene la UNIÓN de
 * las funcionalidades de sus suscripciones vigentes (ERP, WEB…); una ruta que no pertenece a ninguna es del núcleo.
 */
class Modulos
{
    /** @return array<string, array{0: string, 1: string, 2: array<int, string>}>  clave => [nombre, descripción, rutas] */
    public static function catalogo(): array
    {
        return config('suscripciones.funcionalidades', []);
    }

    public static function nombre(string $funcionalidad): string
    {
        return self::catalogo()[$funcionalidad][0] ?? $funcionalidad;
    }

    /** Funcionalidad a la que pertenece una ruta (null = núcleo) */
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

    /** Alguna suscripción vigente de la empresa da la funcionalidad (las del núcleo siempre) */
    public static function incluido(?string $funcionalidad, ?int $empresaId): bool
    {
        if (! $funcionalidad || ! $empresaId) {
            return true;
        }

        return in_array($funcionalidad, app(SuscripcionService::class)->funcionalidades($empresaId), true);
    }

    public static function rutaIncluida(?string $ruta, ?int $empresaId): bool
    {
        return self::incluido(self::deRuta($ruta), $empresaId);
    }

    /**
     * La suscripción que deja la funcionalidad en solo lectura: todas las que la dan están suspendidas o canceladas
     * (devuelve una de ellas). Null = se puede operar (alguna está al día, o la empresa no tiene planes).
     */
    public static function bloqueante(?string $funcionalidad, ?int $empresaId): ?Suscripcion
    {
        if (! $empresaId) {
            return null;
        }
        $otorgan = app(SuscripcionService::class)->otorgan($empresaId, $funcionalidad);

        return $otorgan->contains(fn (Suscripcion $s) => $s->permiteOperar()) ? null : $otorgan->first();
    }

    /**
     * La ruta se puede abrir ahora: su funcionalidad está en algún plan y, si es una página bloqueada en modo solo
     * lectura (formularios de alta y edición), alguna de las suscripciones que la dan está al día. Lo usan el menú
     * lateral y la directiva @ruta('…') de las vistas.
     */
    public static function rutaDisponible(string $ruta, ?int $empresaId): bool
    {
        $funcionalidad = self::deRuta($ruta);
        if (! self::incluido($funcionalidad, $empresaId)) {
            return false;
        }

        return ! ($empresaId && VerificarSuscripcion::bloqueadaSuspendida($ruta) && self::bloqueante($funcionalidad, $empresaId));
    }
}
