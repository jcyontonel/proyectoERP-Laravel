<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Roles GLOBALES (empresa_id NULL en roles): existen una sola vez y se asignan
     * a cada usuario por empresa (model_has_roles.empresa_id).
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId(null);

        Permission::query()->delete();
        Role::query()->delete();

        $permisos = [
            // Ventas
            'ver_ventas',
            'emitir-ventas',
            'editar_ventas',
            'eliminar_ventas',
            'emitir-notas',
            // Compras
            'gestionar-compras',
            'gestionar-proveedores',
            // Inventario
            'gestionar-productos',
            'gestionar-inventario',
            'aprobar-ajustes',
            // Caja y tesorería
            'gestionar-caja',
            'registrar-pagos',
            // Maestros y configuración
            'gestionar-clientes',
            'ver_usuarios',
            'crear_usuarios',
            'gestionar-usuarios',
            'gestionar-empresas',
            'configurar-correlativos',
            'configurar-impuestos',
            // Reportes
            'ver-reportes',
        ];

        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso]);
        }

        $admin    = Role::create(['name' => 'Administrador']);
        $vendedor = Role::create(['name' => 'Vendedor']);
        $almacen  = Role::create(['name' => 'Almacenero']);
        $contador = Role::create(['name' => 'Contador']);

        $admin->givePermissionTo(Permission::all());
        $vendedor->givePermissionTo([
            'ver_ventas',
            'emitir-ventas',
            'gestionar-clientes',
            'gestionar-caja',
            'registrar-pagos',
        ]);
        $almacen->givePermissionTo([
            'gestionar-productos',
            'gestionar-inventario',
            'gestionar-compras',
            'gestionar-proveedores',
        ]);
        $contador->givePermissionTo([
            'ver_ventas',
            'emitir-ventas',
            'emitir-notas',
            'registrar-pagos',
            'configurar-correlativos',
            'configurar-impuestos',
            'ver-reportes',
        ]);
    }
}
