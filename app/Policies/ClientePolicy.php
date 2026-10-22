<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;
use App\Support\EmpresaActual;

/**
 * El cliente es global: solo lo ve la empresa que lo tiene vinculado (cliente_empresa).
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
        return $this->vinculado($user, $cliente);
    }

    public function create(User $user): bool
    {
        return EmpresaActual::id($user) !== null && $user->can('gestionar-clientes');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $this->vinculado($user, $cliente) && $user->can('gestionar-clientes');
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $this->update($user, $cliente);
    }

    private function vinculado(User $user, Cliente $cliente): bool
    {
        $empresaId = EmpresaActual::id($user);

        return $empresaId !== null && $cliente->empresas()->where('empresas.id', $empresaId)->exists();
    }
}
