<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tablas
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::query()->delete();
        Role::query()->delete();

        // Crear permisos
        $permisos = [
            'ver_ventas',
            'emitir-ventas',
            'editar_ventas',
            'eliminar_ventas',
            'ver_usuarios',
            'crear_usuarios',
            'gestionar-clientes',
            'gestionar-productos',
            'gestionar-usuarios',
            'gestionar-empresas',
            'configurar-correlativos',
            'configurar-impuestos',
        ];

        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso]);
        }

        // Crear roles
        $admin = Role::create(['name' => 'Administrador']);
        $vendedor = Role::create(['name' => 'Vendedor']);
        $contador = Role::create(['name' => 'Contador']);

        // Asignar permisos por rol
        $admin->givePermissionTo(Permission::all());
        $vendedor->givePermissionTo([
            'ver_ventas',
            'gestionar-clientes', 
            'emitir-ventas'
        ]);
        $contador->givePermissionTo([
            'ver_ventas', 
            'emitir-ventas', 
            'configurar-correlativos', 
            'configurar-impuestos'
        ]);
    }
}
