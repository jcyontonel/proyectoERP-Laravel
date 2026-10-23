<?php

namespace App\Modulos\Suscripciones\Support;

use App\Modulos\Suscripciones\Http\Middleware\VerificarSuscripcion;
use App\Modulos\Suscripciones\Models\ProductoPlataforma;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use Illuminate\Support\Str;

/**
 * Módulos del sistema que se reparten entre los planes de cada producto (config suscripciones.modulos para
 * el ERP y suscripciones.modulos_web para la Página web). Una ruta que no pertenece a ningún módulo es de la
 * base del ERP: la tienen todos los planes.
 */
class Modulos
{
    /** @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: string}>  clave => [nombre, descripción, rutas, producto] */
    public static function catalogo(): array
    {
        $tag = fn (array $modulos, string $producto) => array_map(fn (array $m) => [$m[0], $m[1], $m[2], $producto], $modulos);

        // Los de la web primero: sus patrones son más específicos (web.pedidos.* antes que web.*)
        return $tag(config('suscripciones.modulos_web', []), ProductoPlataforma::WEB)
            + $tag(config('suscripciones.modulos', []), ProductoPlataforma::ERP);
    }

    public static function nombre(string $modulo): string
    {
        return self::catalogo()[$modulo][0] ?? $modulo;
    }

    /** Producto al que pertenece el módulo (la base es del ERP) */
    public static function producto(?string $modulo): string
    {
        return $modulo ? (self::catalogo()[$modulo][3] ?? ProductoPlataforma::ERP) : ProductoPlataforma::ERP;
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

    /** El plan de la empresa en el producto del módulo lo incluye (los de la base siempre) */
    public static function incluido(?string $modulo, ?int $empresaId): bool
    {
        if (! $modulo || ! $empresaId) {
            return true;
        }
        $modulos = app(SuscripcionService::class)->modulos($empresaId, self::producto($modulo));

        return $modulos === null || in_array($modulo, $modulos, true);
    }

    public static function rutaIncluida(?string $ruta, ?int $empresaId): bool
    {
        return self::incluido(self::deRuta($ruta), $empresaId);
    }

    /**
     * La ruta se puede abrir ahora: su módulo está en el plan y, si la suscripción de su producto está
     * suspendida, no es una de las páginas bloqueadas (formularios de alta y edición). Lo usan el menú
     * lateral y la directiva @ruta('…') de las vistas.
     */
    public static function rutaDisponible(string $ruta, ?int $empresaId): bool
    {
        if (! self::rutaIncluida($ruta, $empresaId)) {
            return false;
        }
        if ($empresaId && VerificarSuscripcion::bloqueadaSuspendida($ruta)) {
            $s = app(SuscripcionService::class)->vigente($empresaId, self::producto(self::deRuta($ruta)));

            return ! $s || $s->permiteOperar();
        }

        return true;
    }
}
