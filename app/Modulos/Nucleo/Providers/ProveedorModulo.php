<?php

namespace App\Modulos\Nucleo\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base de los providers de módulo (app/Modulos/{Modulo}/Providers). Cada módulo trae sus propias:
 *  - migraciones: database/migrations (Laravel las ordena por nombre junto con las demás)
 *  - vistas: resources/views; se registran con el namespace del módulo ('erp::ventas.index') y también
 *    en la ruta global, así los nombres de siempre ('ventas.index') siguen funcionando
 *  - rutas: routes/*.php, publicadas en su contexto (dominio o prefijo, config/modulos.php)
 */
abstract class ProveedorModulo extends ServiceProvider
{
    /** Carpeta raíz del módulo (app/Modulos/{Modulo}) */
    protected function carpeta(string $ruta = ''): string
    {
        return dirname((new ReflectionClass(static::class))->getFileName(), 2) . ($ruta ? '/' . ltrim($ruta, '/') : '');
    }

    /** Migraciones y vistas del módulo */
    protected function cargarRecursos(string $alias): void
    {
        if (is_dir($m = $this->carpeta('database/migrations'))) {
            $this->loadMigrationsFrom($m);
        }
        if (is_dir($v = $this->carpeta('resources/views'))) {
            $this->loadViewsFrom($v, $alias);
            View::addLocation($v);
        }
    }

    /**
     * Rutas web del módulo en su contexto. $autenticado: dentro del middleware "auth".
     */
    protected function rutasWeb(string $archivo, string $contexto = 'erp', bool $autenticado = true): void
    {
        $this->publicar($this->carpeta("routes/{$archivo}"), $contexto, $autenticado ? ['web', 'auth'] : ['web']);
    }

    /** Rutas de API del módulo (grupo "api", contexto api por defecto) */
    protected function rutasApi(string $archivo, string $contexto = 'api'): void
    {
        $this->publicar($this->carpeta("routes/{$archivo}"), $contexto, ['api']);
    }

    private function publicar(string $archivo, string $contexto, array $middleware): void
    {
        if ($this->app->routesAreCached() || ! is_file($archivo)) {
            return;
        }
        $c = config("modulos.contextos.{$contexto}", []);
        $grupo = Route::middleware($middleware);
        if (filled($c['dominio'] ?? null)) {
            $grupo->domain($c['dominio']);
        }
        if (filled($c['prefijo'] ?? null)) {
            $grupo->prefix($c['prefijo']);
        }
        $grupo->group($archivo);
    }
}
