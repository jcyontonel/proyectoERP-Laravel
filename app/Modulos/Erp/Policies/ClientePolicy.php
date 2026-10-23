<?php

namespace App\Modulos\Erp\Policies;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Support\EmpresaActual;

/**
 * El cliente es de una empresa: solo lo ve esa empresa.
 * Registrar, editar y quitar exige "gestionar-clientes". El Administrador pasa por Gate::before.
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return EmpresaActual::id($user) !== null;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->esDeSuEmpresa($user, $cliente);
    }

    public function create(User $user): bool
    {
        return EmpresaActual::id($user) !== null && $user->can('gestionar-clientes');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $this->esDeSuEmpresa($user, $cliente) && $user->can('gestionar-clientes');
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $this->update($user, $cliente);
    }

    private function esDeSuEmpresa(User $user, Cliente $cliente): bool
    {
        $empresaId = EmpresaActual::id($user);

        return $empresaId !== null && (int) $cliente->empresa_id === $empresaId;
    }
}
