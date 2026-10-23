<?php

namespace App\Modulos\Nucleo\Support;

use App\Modulos\Suscripciones\Support\Modulos;

use App\Modulos\Nucleo\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Arma el menú de config/menu.php para un usuario: filtra por permiso, por los módulos del plan de la
 * empresa y (si la suscripción está suspendida) por las páginas bloqueadas; resuelve la URL
 * (o "Próximamente" si el módulo aún no tiene ruta) y marca el ítem activo.
 */
class Menu
{
    /**
     * @param  array<string, int>  $contadores  burbujas (NotificacionService::contadores)
     * @return Collection<int, object{titulo: ?string, config: bool, abierta: bool, items: Collection}>
     */
    public static function para(User $user, array $contadores = []): Collection
    {
        $actual = Route::currentRouteName();
        $empresaId = EmpresaActual::id($user);

        return collect(config('menu.secciones'))
            ->map(function (array $s) use ($user, $contadores, $actual, $empresaId) {
                $items = collect($s['items'])
                    ->filter(fn (array $i) => empty($i['permiso']) || $user->can($i['permiso']))
                    // Módulo que el plan no incluye, o página bloqueada por suscripción suspendida: no se muestra
                    ->filter(fn (array $i) => Modulos::rutaDisponible($i['route'], $empresaId))
                    ->map(fn (array $i) => self::item($i, $contadores, $actual))
                    ->values();

                return (object) [
                    'titulo'  => $s['titulo'],
                    'config'  => (bool) ($s['config'] ?? false),
                    'oculta'  => (bool) ($s['oculta'] ?? false),
                    'abierta' => $items->contains('activo', true),
                    'items'   => $items,
                ];
            })
            ->filter(fn ($s) => $s->items->isNotEmpty())
            ->values();
    }

    /** Ítem del menú por su clave (para la página "Próximamente") */
    public static function buscar(string $clave): ?array
    {
        return collect(config('menu.secciones'))
            ->flatMap(fn ($s) => collect($s['items'])->map(fn ($i) => $i + ['seccion' => $s['titulo']]))
            ->firstWhere('clave', $clave);
    }

    private static function item(array $i, array $contadores, ?string $actual): object
    {
        $existe = Route::has($i['route']);
        $patrones = $i['activo'] ?? [$i['route']];

        return (object) [
            'clave'    => $i['clave'],
            'label'    => $i['label'],
            'icon'     => $i['icon'],
            'pronto'   => ! $existe,
            'url'      => $existe ? route($i['route']) : route('pronto', $i['clave']),
            'activo'   => $existe
                ? ($actual && collect($patrones)->contains(fn ($p) => str($actual)->is($p)))
                : request()->routeIs('pronto') && request()->route('modulo') === $i['clave'],
            'contador' => (int) ($contadores[$i['contador'] ?? ''] ?? 0),
        ];
    }
}
