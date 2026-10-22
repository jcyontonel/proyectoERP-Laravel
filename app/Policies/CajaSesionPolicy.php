<?php

namespace App\Policies;

use App\Models\CajaSesion;
use App\Models\User;

/**
 * Cada cajero ve sus turnos; con "ver-reportes" (administrador, contador) se ven los de todos.
 * La empresa ya la garantiza el binding de rutas (EsDeEmpresa).
 */
class CajaSesionPolicy
{
    public function view(User $user, CajaSesion $sesion): bool
    {
        return $sesion->user_id === $user->id || $user->can('ver-reportes');
    }
}
