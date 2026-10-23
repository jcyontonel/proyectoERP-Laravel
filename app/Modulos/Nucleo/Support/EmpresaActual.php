<?php

namespace App\Modulos\Nucleo\Support;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use Illuminate\Support\Collection;

class EmpresaActual
{
    /** Empresas activas por usuario, para no repetir la consulta en cada llamada de la misma petición */
    private static array $cache = [];

    /** Instancia de la aplicación a la que pertenece la caché (nueva por petición, prueba u Octane) */
    private static ?object $app = null;

    /**
     * Empresa con la que trabaja el usuario: la guardada en sesión (si le pertenece)
     * o la primera activa que tenga asignada en empresa_user.
     */
    public static function id($user): ?int
    {
        $ids = self::disponibles($user)->pluck('id');

        $enSesion = session('empresa_id');
        if ($enSesion && $ids->contains($enSesion)) {
            return (int) $enSesion;
        }

        // Empresa de inicio elegida en Preferencias (si sigue teniendo acceso a ella)
        $preferida = $user instanceof User ? $user->preferencias()->empresa_inicio_id : null;
        if ($preferida && $ids->contains($preferida)) {
            return (int) $preferida;
        }

        // Sin empresa asignada no hay acceso (antes caía en la primera empresa activa del sistema)
        return $ids->isNotEmpty() ? (int) $ids->first() : null;
    }

    public static function empresa($user): ?Empresa
    {
        $id = self::id($user);

        return $id ? self::disponibles($user)->firstWhere('id', $id) : null;
    }

    /** @return Collection<int, Empresa> */
    public static function disponibles($user): Collection
    {
        if (! $user) {
            return collect();
        }

        if (self::$app !== app()) {
            self::$cache = [];
            self::$app = app();
        }

        return self::$cache[$user->getKey()] ??= Empresa::query()
            ->activo()
            ->whereHas('usuarios', fn ($q) => $q->whereKey($user->getKey()))
            ->orderBy('id')
            ->get();
    }

    /** Cambia la empresa de trabajo (solo a una que el usuario tenga asignada) */
    public static function cambiar(User $user, int $empresaId): bool
    {
        if (! self::disponibles($user)->contains('id', $empresaId)) {
            return false;
        }

        session(['empresa_id' => $empresaId]);

        return true;
    }

    /** Para pruebas o tras asignar empresas en la misma petición */
    public static function olvidar(): void
    {
        self::$cache = [];
    }
}
