<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;
use App\Support\EmpresaActual;

/**
 * Cualquier usuario de la empresa puede consultar el catálogo (vendedores revisan stock y precios);
 * crear, editar y eliminar exige "gestionar-productos". El Administrador pasa por Gate::before.
 */
class ProductoPolicy
{
    public function viewAny(User $user): bool
    {
        return EmpresaActual::id($user) !== null;
    }

    public function view(User $user, Producto $producto): bool
    {
        return $this->esDeSuEmpresa($user, $producto);
    }

    public function create(User $user): bool
    {
        return EmpresaActual::id($user) !== null && $user->can('gestionar-productos');
    }

    public function update(User $user, Producto $producto): bool
    {
        return $this->esDeSuEmpresa($user, $producto) && $user->can('gestionar-productos');
    }

    public function delete(User $user, Producto $producto): bool
    {
        return $this->update($user, $producto);
    }

    /** Ver costos y márgenes */
    public function verCostos(User $user): bool
    {
        return $user->can('gestionar-productos') || $user->can('ver-reportes');
    }

    private function esDeSuEmpresa(User $user, Producto $producto): bool
    {
        return $producto->empresa_id === EmpresaActual::id($user);
    }
}
